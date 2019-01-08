<?php

namespace Dan\Shopify\Laravel\Events\Stores;

use Dan\Shopify\Laravel\Models\Store;

/**
 * Class UninstallSuggested
 */
class UninstallSuggested
{
    /** @var Store $store */
    protected $store;

    /** @var $status_code */
    protected $status_code;

    /**
     * UninstallRecommended constructor.
     *
     * @param Store $store
     * @param $status_code
     */
    public function __construct(Store $store, $status_code)
    {
        $this->store = $store;
        $this->status_code = $status_code;
    }

    /**
     * @return Store
     */
    public function getStore(): Store
    {
        return $this->store;
    }

    /**
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->status_code;
    }
}