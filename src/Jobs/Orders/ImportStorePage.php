<?php

namespace Dan\Shopify\Laravel\Jobs\Orders;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Events\Stores\OrdersSynced;
use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Laravel\Support\OrderService;
use Dan\Shopify\Laravel\Support\Util;
use DB;
use Exception;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Queue\MaxAttemptsExceededException;

/**
 * Class ImportStorePage
 */
class ImportStorePage extends AbstractStoreJob
{
    /** @var int $tries */
    public $tries = 1;

    /** @var Store $store */
    protected $store;

    /** @var array $pages */
    protected $pages;

    /** @var string $params */
    protected $params;

    /** @var bool $dryrun */
    protected $dryrun;

    /** @var bool $now */
    protected $now;

    /** @var int $page */
    protected $page;

    /** @var int $total */
    protected $total;

    /** @var int $limit */
    protected $limit;

    /** @var array $started */
    protected $started;

    /**
     * ImportStorePage constructor.
     *
     * @param Store $store
     * @param array $pages
     * @param array $params
     * @param string $connection
     * @param bool $dryrun
     */
    public function __construct(Store $store, array $pages = [1], $params = [], $connection = 'sync', $dryrun = false)
    {
        parent::__construct($store);

        $this->params = $params + $this->getDefaultParams();
        $this->pages = $pages;
        $this->connection = $connection;
        $this->dryrun = $dryrun;

        $this->page = array_shift($this->pages);
        $this->total = $this->page + count($this->pages);
    }

    /**
     * @return array
     */
    public function getDefaultParams()
    {
        return [
            'limit' => config('shopify.sync.limit'),
            'order' => 'created_at asc',
            'created_at_min' => $this->getStore()->last_order_import_at
                ? $this->getStore()->last_order_import_at->format('c')
                : $this->getStore()->created_at->format('c')
        ];
    }

    /**
     * @return $this
     */
    public function handle()
    {
        $this->started = [
            'microtime' => microtime(true),
            'carbon' => now()
        ];

        ini_set('max_execution_time', config('shopify.sync.max_execution_time'));

        $store = $this->store;
        $params = $this->params;
        $page = $this->page;
        $page_sleep = config('shopify.sync.sleep_between_page_requests');
        $connection = $this->connection;
        $verb = $connection == 'sync' ? 'dispatched' : 'queued';

        try {
            $importing = [];
            $ignoring = [];

            $this->msg('initiated', compact('page', 'params'), 'info');

            // Iterate pages of orders from Shopify
            $api_orders = $this->getApiClient()
                ->orders
                ->get($params + compact('page'));

            $this->msg('received', ['count' => count($api_orders)], 'info');

            $existing_db_orders = $this->getExistingOrderIds($api_orders);
            
            $found = 0;

            foreach ($api_orders as $api_order) {
                $id = (string) $api_order['id'];

                if (! isset($api_order['line_items'])) {
                    $this->msg("order:{$id}:no_line_items", $api_order, 'warning');
                    continue;
                }

                if (in_array($id, $existing_db_orders)) {
                    $this->msg("order:{$id}:already_exists", $api_order, 'warning');
                    continue;
                }

                if (OrderService::filter($store, $api_order)) {
                    $importing[$id] = $api_order['number'];
                } else {
                    $ignoring[$id] = $api_order['number'];
                    continue;
                }

                if ($this->dryrun) {
                    $this->msg("order:{$id}:dryrun", [], 'info');
                    continue;
                }

                $job = new ImportOrder($store, $api_order);

                $this->msg("order:{$id}:{$verb}", [], 'info');

                $found++;

                $connection == 'sync'
                    ? dispatch_now($job)
                    : dispatch($job)->onConnection($connection);
            }

            if (empty($importing)) {
                $this->msg('no_orders_queued',  [], 'info');
            } else {
                $this->msg('orders_queued', $importing, 'info');
            }

            if (! empty($ignoring)) {
                $this->msg('orders_ignored', $ignoring, 'info');
            }

            // Set last_order_import_at to newest order created_at timestamp.
            if (! empty($api_orders) && ($found || $this->page != $this->total)) {
                $last_order_import_at = (Carbon::parse(array_last($api_orders)['created_at']))
                    ->setTimeZone(config('app.timezone'));
                $store->update(compact('last_order_import_at'));
            // Or, set last_order_import_at to now if no orders!
            } else {
                // In case the job took awhile to run.
                $last_order_import_at = new Carbon('30 minutes ago');
                $store->update(compact('last_order_import_at'));
            }

            // Is this the last page / order?
            if (empty($api_orders) || empty($this->pages)) {
                ImportStore::unlock($store);
                $this->msg('completed', [], 'info');
                event(new OrdersSynced($this->store));
            } else {
                sleep($page_sleep);
                $next_page = new static($store, $this->pages, $this->params, $connection, $this->dryrun);
                $connection == 'sync'
                    ? dispatch_now($next_page)
                    : dispatch($next_page)->onConnection($connection);
            }
        } catch (ClientException $ce) {
            $data = Util::exceptionArr($ce, compact( 'page', 'total', 'params'));
            $this->msg('api_failed', $data, 'error');
        } catch (Exception $e) {
            $data = Util::exceptionArr($e, compact( 'page', 'total', 'params'));
            $this->msg('failed', $data, 'error');
        }

        return $this;
    }

    /**
     * @param Exception $e
     */
    public function failed(Exception $e)
    {
        switch (true) {
            case $e instanceof MaxAttemptsExceededException:
                $this->msg('queue_max_attempts_failure', $this->params , 'error');
                $limit = intval($this->params['limit'] / 2);

                ImportStore::unlock($store = $this->getStore());

                // If we can't page at least 2, give up!!!
                if ($limit == 1) {
                    $this->msg('unable_to_page', [], 'error');

                // Otherwise, try a smaller page size.
                } else {
                    $this->msg('retrying_with_new_limit', compact('limit'));

                    $created_at_min = $store->last_order_import_at
                        ? $store->last_order_import_at->format('c')
                        : $store->created_at->format('c');

                    $job = new ImportStore(
                        $store = $this->getStore(),
                        $params = compact('created_at_min', 'limit') + $this->params,
                        $connection = $this->connection);

                    $connection == 'sync'
                        ? dispatch_now($job)
                        : dispatch($job)->onConnection($connection);
                }
                break;
            default:
                $this->msg('sqs_failed', ['msg' => $e->getMessage()], 'emergency');
        }
    }

    /**
     * @param array $api_orders
     * @return array
     */
    protected function getExistingOrderIds(array $api_orders): array
    {
        $api_order_ids = array_pluck($api_orders, 'id');

        $existing_db_orders = DB::table('orders')
            ->where('store_type', Store::class)
            ->where('store_id', $this->getStore()->getKey())
            ->whereIn('store_order_id', $api_order_ids)
            ->pluck('store_order_id')
            ->all();

        $this->msg('existing', compact('existing_db_orders'), 'info');

        return $existing_db_orders;
    }
}
