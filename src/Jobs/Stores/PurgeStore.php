<?php

namespace Dan\Shopify\Laravel\Jobs\Stores;

use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
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
                $customer_model = config('shopify.customers.model');
                $customer_model::forStore($store)->withTrashed()->forceDelete();
                $this->resetTimestamp('customers');
            }

            if (in_array('orders', $this->entities)) {
                $order_model = config('shopify.orders.model');
                $order_model::forStore($store)->withTrashed()->forceDelete();
                $this->resetTimestamp('orders');
            }

            if (in_array('products', $this->entities)) {
                $product_model = config('shopify.products.model');
                $product_model::forStore($store)->withTrashed()->forceDelete();
                $this->resetTimestamp('products');
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
