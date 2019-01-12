<?php

namespace Dan\Shopify\Laravel\Jobs\Stores;

use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Laravel\Support\RateLimitedRequest;
use Dan\Shopify\Laravel\Support\StoreService;
use Dan\Shopify\Laravel\Support\Util;
use Exception;
use Log;

/**
 * Class UpdateStore
 */
class UpdateStore extends AbstractStoreJob
{
    /** @var array $counts */
    protected $counts;

    /**
     * UpdateStore constructor.
     *
     * @param Store $store
     * @param array $counts
     */
    public function __construct(Store $store, $counts = [])
    {
        parent::__construct($store);
        $this->counts = $counts;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $store = $this->getStore();

        try {
            $this->handleCounts();

            $data = RateLimitedRequest::respond(function() use ($store) {
                return $store->getApiClient()->shop();
            });

            (new StoreService($data, $store))->update();
        } catch (Exception $e) {
            $this->handleException($e);
        }

    }

    /**
     * @return void
     */
    protected function handleCounts(): void
    {
        $store = $this->getStore();

        if (in_array('customer_count', $this->counts)) {
            $store->customer_count = RateLimitedRequest::respond(function () use ($store) {
                return $store->getApiClient()->customers->count();
            });
        }

        if (in_array('order_count', $this->counts)) {
            $store->order_count = RateLimitedRequest::respond(function () use ($store) {
                return $store->getApiClient()->orders->count();
            });
        }

        if (in_array('product_count', $this->counts)) {
            $store->product_count = RateLimitedRequest::respond(function () use ($store) {
                return $store->getApiClient()->products->count();
            });
        }

        if (! empty($this->counts)) {
            $store->save();
        }
    }

    /**
     * @param Exception $e
     * @return void
     */
    protected function handleException(Exception $e): void
    {
        $store = $this->getStore();
        $status_code = Util::exceptionStatusCode($e);
        $msg = "platform:" . __CLASS__ . ":cmd:{$store->myshopify_domain}:api_failure:$status_code";
        Log::error($msg, Util::exceptionArr($e));
    }
}
