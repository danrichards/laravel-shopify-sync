<?php

namespace Dan\Shopify\Laravel\Events\Orders;

use Dan\Shopify\Laravel\Models\Order;
use Illuminate\Queue\SerializesModels;

/**
 * Class AbstractOrderEvent
 */
abstract class AbstractOrderEvent
{
    use SerializesModels;

    /** @var Order $order */
    protected $order;

    /**
     * AbstractOrderEvent constructor.
     *
     * @param Order $order
     */
    public function __construct(Order $order)
    {
        $this->order = $order->fresh();
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->order;
    }
}
