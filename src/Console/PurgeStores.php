<?php

namespace Dan\Shopify\Laravel\Console;

use Dan\Shopify\Laravel\Jobs\Stores\PurgeStore;
use Dan\Shopify\Laravel\Models\Store;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class PurgeStores
 */
class PurgeStores extends AbstractCommand
{
    /** @var string $signature */
    protected $signature = 'shopify:purge:stores {--store_ids=any} {--store} {--all} {--customers} {--orders} {--products} {--connection=sync}';

    /** @var string $description */
    protected $description = 'Update Shopify Store Information';

    /** @var int $chunk_size */
    protected static $chunk_size = 100;

    /**
     * @return array
     */
    protected function getEntities()
    {
        return $this->option('all')
            ? ['store', 'customers', 'orders', 'products']
            : array_filter([
                $this->option('store') ? 'store' : null,
                $this->option('customers') ? 'customers' : null,
                $this->option('orders') ? 'orders' : null,
                $this->option('products') ? 'products' : null,
            ]);
    }

    /**
     * @return Store|Builder
     */
    protected function getQuery()
    {
        $store_model = config('shopify.stores.model');
        /** @var Store $store_model */
        $store_model = new $store_model;

        return $store_model
            ->forInstalled()
            ->when($this->optionIds('store_ids'), function (Builder $q, $store_ids) {
                $q->whereIn('stores.id', $store_ids);
            });
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $connection = $this->option('connection');
        $entities = $this->getEntities();

        $this->getQuery()
            ->chunk(static::$chunk_size, function($stores) use($entities, $connection) {
                /** @var Store $store */
                foreach ($stores as $store) {
                    $this->handleStore($store, $entities, $connection);
                }
            });
    }

    /**
     * @param Store $store
     * @param array $entities
     * @param $connection
     * @return Store
     */
    protected function handleStore(Store $store, array $entities = [], $connection = 'sync')
    {
        $connection == 'sync'
            ? dispatch_now(new PurgeStore($store, $entities))
            : dispatch(new PurgeStore($store, $entities))
                ->onConnection($connection);

        $this->info("Purge for Store({$store->getKey()}): {$store->myshopify_domain}, has completed.");

        return $store;
    }
}
