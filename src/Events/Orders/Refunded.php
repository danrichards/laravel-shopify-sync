<?php

namespace Dan\Shopify\Laravel\Events\Orders;

use App\Models\Order;

/**
 * Class Refunded
 */
class Refunded extends AbstractOrderEvent
{
    /** @var array $data */
    protected $data;

    /**
     * RefundRefused constructor.
     *
     * @param Order $order
     * @param array $data
     */
    public function __construct(Order $order, array $data = [])
    {
        parent::__construct($order);

        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }
}