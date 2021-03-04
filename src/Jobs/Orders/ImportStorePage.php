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
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;

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

    /** @var BaseCollection $existing_order_ids */
    protected $existing_order_ids;

    /** @var bool $filter_existing */
    protected $filter_existing;

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
     * @param int $limit
     */
    protected function failWithRetry($limit): void
    {
        $this->msg('retrying_with_new_limit', compact('limit'));

        $created_at_min = $this->getStore()->last_order_import_at
            ? $this->getStore()->last_order_import_at->format('c')
            : $this->getStore()->created_at->format('c');

        $job = new ImportStore(
            $store = $this->getStore(),
            $params = compact('created_at_min', 'limit') + $this->params,
            $connection = $this->connection);

        $connection == 'sync'
            ? dispatch_now($job)
            : dispatch($job)->onConnection($connection);
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
                    $this->failWithRetry($limit);
                }
                break;
            default:
                $this->msg('sqs_failed', ['msg' => $e->getMessage()], 'emergency');
        }
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
     * Keyed by store_order_id
     *
     * @param BaseCollection $api_orders
     * @return BaseCollection
     */
    protected function getExistingOrderIds(BaseCollection $api_orders): BaseCollection
    {
        if (! is_null($this->existing_order_ids)) {
            return $this->existing_order_ids;
        }

        $ids = $api_orders->pluck('id')->all();

        $this->existing_order_ids = $ids = DB::table('orders')
            ->where('store_type', Store::class)
            ->where('store_id', $this->getStore()->getKey())
            ->whereIn('store_order_id', $ids)
            ->pluck('id', 'store_order_id');

        $this->msg('existing', compact('ids'), 'info');

        return $this->existing_order_ids;
    }

    /**
     * @param BaseCollection $api_orders
     * @return Carbon|null
     */
    protected function getLastOrderImportAt($api_orders)
    {
        $latest = $api_orders
            ->sortByDesc('created_at')
            ->first();

        return $latest ? Carbon::parse($latest['created_at']) : null;
    }

    /**
     * @return BaseCollection
     * @throws \Dan\Shopify\Exceptions\InvalidOrMissingEndpointException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function getOrdersFromApi(): BaseCollection
    {
        // Iterate pages of orders from Shopify
        $api = $this->getApiClient();

        $params = isset($this->params['page_info'])
            ? Arr::only($this->params, ['limit', 'page_info'])
            : $this->params;

        $api_orders = $api->orders->get($params);

        // set next page info
        $this->params['page_info'] = $api->cursors['next'] ?? null;

        $this->msg('received', ['count' => count($api_orders)], 'info');

        return collect($api_orders);
    }

    /**
     * @return $this
     */
    public function handle()
    {
        $this->handleStart();

        try {
            $api_orders = $this->getOrdersFromApi();
            $last_order_import_at = $this->getLastOrderImportAt($api_orders);

            $existing = $this->getExistingOrderIds($api_orders);
            $filter = config('shopify.orders.import_filter');

            $dispatched = [];

            $api_orders->when($this->filter_existing,
                function(BaseCollection $col) use ($existing) {
                    return $col->reject(function(array $api_order) use ($existing) {
                        return $existing->has($api_order['id']);
                    });
                })
                ->filter(function(array $api_order) use ($filter) {
                    return OrderService::filter($this->getStore(), $api_order);
                })
                ->each(function(array $api_order) use (&$dispatched) {
                    $this->handleDispatchImportOrder($api_order);
                    $dispatched[] = $api_order['id'];
                });

            $this->handleCurrentPageFinished($last_order_import_at);

            // Is this the last page / product?
            if (empty($this->params['page_info'])) {
                $this->handleLastPageFinished();
            } else {
                $this->handleDispatchNextPage();
            }
        } catch (ClientException $ce) {
            $this->handleClientException($ce);
        } catch (Exception $e) {
            $this->handleException($e);
        }

        $this->handleFinish();

        return $this;
    }

    /**
     * @param $ce
     * @return void
     */
    protected function handleClientException($ce): void
    {
        $data = Util::exceptionArr($ce, [
            'page' => $this->page,
            'total' => $this->total,
            'params' => $this->params,
        ]);

        $this->msg('api_failed', $data, 'error');
    }

    /**
     * @param Carbon|null $last_order_import_at
     */
    protected function handleCurrentPageFinished(?Carbon $last_order_import_at): void
    {
        if ($last_order_import_at) {
            $this->getStore()->update(compact('last_order_import_at'));
        }
    }

    /**
     * @param array $api_order
     * @return void
     */
    protected function handleDispatchImportOrder($api_order): void
    {
        $job = new ImportOrder($this->getStore(), $api_order);

        if ($this->dryrun) {
            $this->msg("order:{$api_order['id']}:dryrun", [], 'info');
            return;
        }

        $this->connection == 'sync'
            ? dispatch_now($job)
            : dispatch($job)->onConnection($this->connection);
    }

    /**
     * @return void;
     */
    protected function handleDispatchNextPage(): void
    {
        $connection = $this->connection;

        sleep(config('shopify.sync.sleep_between_page_requests'));
        $next_page = new static($this->getStore(), $this->pages, $this->params, $connection, $this->dryrun);
        $connection == 'sync'
            ? dispatch($next_page)->onConnection($connection)
            : dispatch_now($next_page);
    }

    /**
     * @param Exception $e
     */
    protected function handleException(Exception $e): void
    {
        $data = Util::exceptionArr($e, [
            'page' => $this->page,
            'total' => $this->total,
            'params' => $this->params,
        ]);

        $this->msg('failed', $data, 'error');
    }

    /**
     * @param array $data
     * @return void
     */
    protected function handleFinish(array $data = []): void
    {
        parent::handleFinish($data + [
            'page' => $this->page,
            'params' => $this->params,
        ]);
    }

    /**
     * @return void
     */
    protected function handleLastPageFinished(): void
    {
        ImportStore::unlock($this->getStore());
        $this->msg('completed', [], 'info');
        event(new OrdersSynced($this->getStore()));
    }

    /**
     * @param array $data
     * @return void
     */
    protected function handleStart(array $data = []): void
    {
        parent::handleStart($data + [
            'page' => $this->page,
            'params' => $this->params,
        ]);
    }
}
