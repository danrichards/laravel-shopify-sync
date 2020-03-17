<?php

namespace Dan\Shopify\Laravel\Events\Stores;

use Dan\Shopify\Laravel\Models\Store;

/**
 * Class UninstallSuggested
 */
class UninstallSuggested extends AbstractStoreEvent
{
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
        parent::__construct($store);
        $this->status_code = $status_code;
    }


    /**
     * @return mixed
     */
    public function getStatusCode()
    {
        return $this->status_code;
    }
}