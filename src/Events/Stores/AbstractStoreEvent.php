<?php

namespace Dan\Shopify\Laravel\Events\Stores;

use Dan\Shopify\Laravel\Models\Store;
use Illuminate\Queue\SerializesModels;

/**
 * Class AbstractStoreEvent
 */
abstract class AbstractStoreEvent
{
    use SerializesModels;

    /** @var Store */
    protected $store;

    /**
     * AbstractStoreEvent constructor.
     *
     * @param Store $store
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * @return Store
     */
    public function getStore(): Store
    {
        return $this->store;
    }
}
