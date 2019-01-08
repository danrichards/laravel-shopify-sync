<?php

namespace Dan\Shopify\Laravel\Events\Stores;

use Dan\Shopify\Laravel\Models\Store;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Class ProductsSynced
 */
class ProductsSynced implements ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var Store */
    protected $store;

    /**
     * StoreSynced constructor.
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
