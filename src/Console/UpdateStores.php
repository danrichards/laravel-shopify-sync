<?php

namespace Dan\Shopify\Laravel\Console;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Jobs\Stores\UpdateStore;
use Dan\Shopify\Laravel\Models\Store;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class UpdateStores
 */
class UpdateStores extends AbstractCommand
{
    /** @var string $signature */
    protected $signature = 'shopify:update:stores {--store_ids=any} {--updated_at_max=} {--customer_count} {--order_count} {--product_count} {--connection=sync}';

    /** @var string $description */
    protected $description = 'Update Shopify Store Information';

    /** @var int $chunk_size */
    protected static $chunk_size = 100;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (config('shopify.sync.enabled', true) != true) {
            $this->error('Sync has been disabled. Please re-enable in your `shopify` configuration.');
            return;
        }

        $connection = $this->option('connection');
        $counts = $this->getCounts();

        $this->getQuery()
            ->chunk(static::$chunk_size, function($stores) use($counts, $connection) {
                /** @var Store $store */
                foreach ($stores as $store) {
                    $this->handleStore($store, $counts, $connection);
                }
            });
    }

    /**
     * @return array
     */
    protected function getCounts()
    {
        return array_filter([
            $this->option('customer_count') ? 'customer_count' : null,
            $this->option('order_count') ? 'order_count' : null,
            $this->option('product_count') ? 'product_count' : null,
        ]);
    }

    /**
     * @param Store $store
     * @param array $counts
     * @param $connection
     * @return Store
     */
    protected function handleStore(Store $store, array $counts = [], $connection = 'sync')
    {
        $connection == 'sync'
            ? dispatch_now(new UpdateStore($store, $counts))
            : dispatch(new UpdateStore($store, $counts))
                ->onConnection($connection);

        $this->info("console:shopify:update:stores:{$store->myshopify_domain}({$store->getKey()}):completed");

        return $store;
    }

    /**
     * @return Store|Builder
     */
    protected function getQuery()
    {
        $store_model = config('shopify.stores.model');
        /** @var Store $store_model */
        $store_model = new $store_model;

        $updated_at_max = $this->option('updated_at_max')
            ? (new Carbon($this->option('updated_at_max')))
                ->format("Y-m-d H:i:s")
            : null;

        return $store_model
            ->forInstalled()
            ->when($this->optionIds('store_ids'), function (Builder $q, $store_ids) {
                $q->whereIn('stores.id', $store_ids);
            })
            ->when($updated_at_max, function(Builder $q, $refresh) {
                $q->where('updated_at', '<', $refresh);
            });
    }

}
