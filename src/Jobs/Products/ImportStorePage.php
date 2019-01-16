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
use Illuminate\Support\Collection as BaseCollection;

/**
 * Class ImportStorePage
 */
class ImportStorePage extends AbstractStoreJob
{
    /** @var bool $dryrun */
    protected $dryrun;

    /** @var BaseCollection $existing_product_ids */
    protected $existing_product_ids;

    /** @var bool $filter_existing */
    protected $filter_existing = true;

    /** @var $finished */
    protected $finished;

    /** @var int $limit */
    protected $limit;

    /** @var int $page */
    protected $page;

    /** @var array $pages */
    protected $pages;

    /** @var string $params */
    protected $params;

    /** @var int $total */
    protected $total;

    /** @var array $started */
    protected $started;

    /** @var Store $store */
    protected $store;

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
     * @param int $limit
     */
    protected function failWithRetry($limit): void
    {
        $this->msg('retrying_with_new_limit', compact('limit'));

        $created_at_min = $this->getStore()->last_product_import_at
            ? $this->getStore()->last_product_import_at->format('c')
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

                // Otherwise, try a smaller page size. Things are timing out in the queue.
                } else {
                    $this->failWithRetry($limit);
                }
                break;
            default:
                $this->msg('sqs_failed', ['msg' => $e->getMessage()], 'emergency');
        }
    }

    /**
     * Keyed by store_product_id
     *
     * @param BaseCollection $api_products
     * @return BaseCollection
     */
    protected function getExistingProductIds(BaseCollection $api_products): BaseCollection
    {
        if (! is_null($this->existing_product_ids)) {
            return $this->existing_product_ids;
        }

        $api_product_ids = $api_products->pluck('id')->all();

        $this->existing_product_ids = DB::table('products')
            ->where('store_type', config('shopify.stores.model'))
            ->where('store_id', $this->getStore()->getKey())
            ->whereIn('store_product_id', $api_product_ids)
            ->pluck('id', 'store_product_id');

        $this->msg('existing', ['ids' => $this->existing_product_ids], 'info');

        return $this->existing_product_ids;
    }

    /**
     * @param BaseCollection $api_products
     * @return Carbon|null
     */
    protected function getLastProductImportAt($api_products)
    {
        $latest = $api_products
            ->sortByDesc('created_at')
            ->first();

        return $latest ? Carbon::parse($latest['created_at']) : null;
    }

    /**
     * @return BaseCollection
     * @throws \Dan\Shopify\Exceptions\InvalidOrMissingEndpointException
     */
    protected function getProductsFromApi(): BaseCollection
    {
        // Iterate pages of products from Shopify
        $api_products = $this->getApiClient()
            ->products
            ->get($this->params + ['page' => $this->page]);

        $this->msg('received', ['count' => count($api_products)], 'info');

        return collect($api_products);
    }

    /**
     * @return $this
     */
    public function handle()
    {
        $this->handleStart();

        try {
            $api_products = $this->getProductsFromApi();
            $last_product_import_at = $this->getLastProductImportAt($api_products);

            $filter = config('shopify.products.import_filter');
            $existing = $this->getExistingProductIds($api_products);

            $dispatched = [];

            $api_products->when($this->filter_existing,
                function(BaseCollection $col) use ($existing) {
                    return $col->reject(function(array $api_product) use ($existing) {
                        return $existing->has($api_product['id']);
                    });
                })
                ->filter(function(array $api_product) use ($filter) {
                    return $filter($api_product);
                })
                ->each(function(array $api_product) use (&$dispatched) {
                    $this->handleDispatchImportProduct($api_product);
                    $dispatched[] = $api_product['id'];
                });

            $this->handleCurrentPageFinished($last_product_import_at);

            // Is this the last page / product?
            if (empty($api_products) || empty($this->pages)) {
                $this->handleLastPageFinished();
            } else {
                $this->handleDispatchNextPage();
            }
        } catch (ClientException $ce) {
            $this->handleClientException($ce);
        } catch (Exception $e) {
            $this->handleException($e);
        }

        $this->handleFinished();

        return $this;
    }

    /**
     * @param $ce
     */
    protected function handleClientException($ce): void
    {
        $data = Util::exceptionArr($ce, $this->getStore()->compact() + [
                'page' => $this->page,
                'total' => $this->total,
                'params' => $this->params,
            ]);

        $this->msg('api_failed', $data, 'error');
    }

    /**
     * @param Carbon|null $last_product_import_at
     */
    protected function handleCurrentPageFinished(?Carbon $last_product_import_at): void
    {
        if ($last_product_import_at) {
            $this->getStore()->update(compact('last_product_import_at'));
        }
    }

    /**
     * @param array $api_product
     */
    protected function handleDispatchImportProduct($api_product): void
    {
        $job = new ImportProduct($this->getStore(), $api_product);

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
        $data = Util::exceptionArr($e, $this->getStore()->compact() + [
                'page' => $this->page,
                'total' => $this->total,
                'params' => $this->params,
            ]);

        $this->msg('failed', $data, 'error');
    }

    /**
     * @return void
     */
    protected function handleFinished(): void
    {
        $this->finished = $finished = [
            'microtime' => microtime(true),
            'carbon' => now()
        ];

        $this->msg('finished', compact('finished') + [
            'page' => $this->page,
            'params' => $this->params,
        ], 'info');
    }

    /**
     * @param Store $store
     */
    protected function handleLastPageFinished(): void
    {
        ImportStore::unlock($this->getStore());
        $this->msg('completed', [], 'info');
        event(new ProductsSynced($this->getStore()));
    }

    /**
     * @return void
     */
    protected function handleStart(): void
    {
        $this->started = $started = [
            'microtime' => microtime(true),
            'carbon' => now()
        ];

        $this->msg('started', compact('started') + [
            'page' => $this->page,
            'params' => $this->params,
        ], 'info');
    }
}
