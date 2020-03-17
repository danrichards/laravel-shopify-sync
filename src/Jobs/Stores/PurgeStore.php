<?php

namespace Dan\Shopify\Laravel\Jobs\Stores;

use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
use Dan\Shopify\Laravel\Models\Order;
use Dan\Shopify\Laravel\Models\Product;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Laravel\Support\Util;
use Exception;

/**
 * Class PurgeStore
 */
class PurgeStore extends AbstractStoreJob
{
    /** @var array $entities */
    protected $entities;

    /**
     * UpdateStore constructor.
     *
     * @param Store $store
     * @param array $entities
     */
    public function __construct(Store $store, $entities = [])
    {
        parent::__construct($store);
        $this->entities = $entities;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $store = $this->getStore();

            if (in_array('customers', $this->entities)) {
                $store->customers()->withTrashed()->forceDelete();
                $this->resetTimestamp('customer');
            }

            if (in_array('orders', $this->entities)) {
                $store->orders()->withTrashed()
                    ->chunk(100, function($orders) {
                        /** @var Order $order */
                        foreach ($orders as $order) {
                            $order->order_items()->withTrashed()->forceDelete();
                        }
                    });

                $store->orders()->withTrashed()->forceDelete();
                $this->resetTimestamp('order');
            }

            if (in_array('products', $this->entities)) {
                $store->products()->withTrashed()
                    ->chunk(100, function($products) {
                        /** @var Product $product */
                        foreach ($products as $product) {
                            $product->variants()->withTrashed()->forceDelete();
                        }
                    });
                
                $store->products()->withTrashed()->forceDelete();
                
                $this->resetTimestamp('product');
            }

            if (in_array('store', $this->entities)) {
                $store->forceDelete();
            }
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * @param Exception $e
     * @return void
     */
    protected function handleException(Exception $e): void
    {
        $this->msg('failed', Util::exceptionArr($e), 'error');
    }

    /**
     * @param string $entity
     */
    protected function resetTimestamp(string $entity)
    {
        if (! in_array('store', $this->entities)) {
            $this->getStore()->update([
                "last_{$entity}_import_at" => $this->getStore()->store_created_at
            ]);
        }
    }
}
