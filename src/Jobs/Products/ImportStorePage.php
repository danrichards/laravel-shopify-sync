<?php

namespace Dan\Shopify\Laravel\Jobs\Products;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Events\Stores\ProductsSynced;
use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
use Dan\Shopify\Laravel\Models\Store;
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
    public function __construct(Store $store, array $pages = [], $params = [], $connection = 'sync', $dryrun = false)
    {
        parent::__construct($store);

        $this->params = $params + /* DEFAULTS */[
            'limit' => config('shopify.sync.limit'),
            'product' => 'created_at asc',
            'created_at_min' => $store->last_product_import_at
                ? $store->last_product_import_at->format('c')
                : $store->created_at->format('c')
        ];
        $this->pages = $pages;
        $this->connection = $connection;
        $this->dryrun = $dryrun;

        $this->page = array_shift($this->pages);
        $this->total = $this->page + count($this->pages);
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

        try {
            $ids_numbers = [];

            $this->msg('initiated', compact('page', 'params'), 'info');

            // Iterate pages of products from Shopify
            $api_products = $this->getApiClient()
                ->products
                ->get($params + compact('page'));

            $this->msg('received', [], 'info');

            $existing_ids = $this->existingProductIds($api_products, $store);

            $this->msg('existing', compact('existing_ids'), 'info');

            $last_product_import_at = null;

            foreach ($api_products as $api_product) {
                $qualifier = config('shopify.products.import_qualifier');

                if ($qualifier($api_product)) {
                    $ids_numbers[] = $api_product['id'];

                    $job = new ImportProduct($store, $api_product);

                    $connection == 'sync'
                        ? dispatch_now($job)
                        : dispatch($job)->onQueue($connection);
                }

                $last_product_import_at = Carbon::parse($api_product['created_at']);
            }

            empty($ids_numbers)
                ? $this->msg('no_products_queued', [], 'info')
                : $this->msg('products_queued', $ids_numbers, 'info');

            if ($last_product_import_at) {
                $store->update(compact('last_product_import_at'));
            }

            // Is this the last page / product?
            if (empty($api_products) || empty($this->pages)) {
                ImportStore::unlock($store);
                $this->msg('completed', [], 'info');
                event(new ProductsSynced($this->store));
            } else {
                sleep($page_sleep);
                $next_page = new static($store, $this->pages, $this->params, $connection, $this->dryrun);
                $connection != 'now'
                    ? dispatch($next_page)->onQueue($connection)
                    : dispatch_now($next_page);
            }
        } catch (ClientException $ce) {
            $data = Util::exceptionArr($ce, $store->compact() + compact( 'page', 'total', 'params'));
            $this->msg('api_failed', $data, 'error');
        } catch (Exception $e) {
            $data = Util::exceptionArr($e, $store->compact() + compact( 'page', 'total', 'params'));
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

                    $created_at_min = $store->last_product_import_at
                        ? $store->last_product_import_at->format('c')
                        : $store->created_at->format('c');

                    $job = new ImportStore(
                        $store = $this->getStore(),
                        $params = compact('created_at_min', 'limit') + $this->params,
                        $connection = $this->connection);

                    $connection != 'now'
                        ? dispatch($job)->onConnection($connection)
                        : dispatch_now($job);
                }
                break;
            default:
                $this->msg('sqs_failed', ['msg' => $e->getMessage()], 'emergency');
        }
    }

    /**
     * @param array $api_products
     * @param Store $store
     * @return array
     */
    protected function existingProductIds(array $api_products, Store $store): array
    {
        $api_product_ids = array_pluck($api_products, 'id');

        $existing_db_products = DB::table('products')
            ->where('store_type', Store::class)
            ->where('store_id', $store->getKey())
            ->whereIn('store_product_id', $api_product_ids)
            ->pluck('store_product_id')
            ->all();

        return $existing_db_products;
    }
}
