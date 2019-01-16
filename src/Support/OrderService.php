<?php

namespace Dan\Shopify\Laravel\Support;

use Dan\Shopify\Laravel\Events\Orders\Created;
use Dan\Shopify\Laravel\Models\Customer;
use Dan\Shopify\Laravel\Models\Order;
use Dan\Shopify\Laravel\Models\OrderItem;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Models\Order as ShopifyOrder;
use BadMethodCallException;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Class OrderService
 */
class OrderService extends AbstractService
{
//    /**
//     * After order creation, these fields may no longer be updated.
//     *
//     * @var array $order_never_updates */
//    public static $order_never_updates = [
//        'user_id',
//        'store_order_id',
//        'order_number',
//        'checkout_id',
//        'checkout_token',
//        'cancelled_at',     // Manual logic below.
//    ];
//
//    /**
//     * After order creation, these item fields may no longer be updated.
//     *
//     * @var array $order_item_never_updates */
//    public static $order_item_never_updates = [
//        'store_line_item_id',
//        'store_variant_id',
//        'store_product_id',
//        'shopify_sku',
//        'quantity',
//        'properties',
//    ];

    /** @var Customer $customer */
    protected $customer;

    /** @var Order|null $order */
    protected $order;

    /** @var array $order_data */
    protected $order_data = [];

    /** @var Collection|null $order_items */
    protected $order_items;

    /** @var Collection|null $filtered_line_items_data */
    protected $filtered_line_items_data = [];

    /** @var Collection|null $filtered_variants */
    protected $filtered_variants;

    /** @var bool $imported */
    protected $imported;

    /** @var bool $updated */
    protected $updated;

    /**
     * ShopifyOrderService constructor.
     *
     * @param Store $store
     * @param array $order_data
     * @param Order|null $order
     */
    public function __construct(Store $store, array $order_data = [], Order $order = null)
    {
        parent::__construct($store);

        $this->init($store, $order_data, $order);

        $this->logTestOrders($order_data);
    }

    /**
     * @param OrderItem $order_item
     */
    private function applyRefundsTo(OrderItem $order_item)
    {
        $line_item_id = $order_item->store_line_item_id;

        if ($refunded_quantity = static::refundedQuantityForLineItem($this->order_data, $line_item_id)) {
            $order_item->quantity -= $refunded_quantity;

            if ($order_item->quantity <= 0) {
                $order_item->quantity = 0;
                $order_item->title .= config('shopify.orders.items.refunded_titles_append');
            } else {
                $order_item->title .= config('shopify.orders.items.partially_refunded_titles_append');
            }
        }
    }

//    /**
//     * @param DateTime|null $cancelled_at
//     * @return $this
//     */
//    public function cancel($cancelled_at = null)
//    {
//        $order = $this->order;
//
//        $cancelled_at = $cancelled_at ?: new Carbon('now');
//        $order->cancelled_at = $cancelled_at;
//
//        if ((! $order->hasGoneToProduction() && $order->isModifiable()) || $order->status == Order::STATUS_CANCELLED) {
//            $order->status = Order::STATUS_CANCELLED;
//            $accepted = true;
//            // The order has gone to production
//        } else {
//            $accepted = false;
//        }
//
//        $order->save();
//
//        $accepted
//            ? event(new Cancelled($order))
//            : event(new CancellationRefused($order));
//
//        return $this;
//    }

    /**
     * @param array $attributes
     * @return Order|null
     * @throws Exception
     */
    public function create(array $attributes = [])
    {
        try {
            DB::beginTransaction();

            $this->customer->save();

            $this->fillMap($this->order_data);
            $this->order->fill($attributes);
            $this->order->save();

            foreach ($this->getFilteredOrderItems() as $oi) {
                $oi->save();
                $this->order_items[$oi->store_line_item_id] = $oi;
            }

//            // Handle any line items that have been refunded.
//            foreach ($this->getFilteredLineItemsData() as $line_item) {
//                $item_quantities[$line_item['id']] = $line_item['quantity'];
//
//                $order_item = $this->fillNewOrderItem($line_item);
//                $order_item->order()->associate($this->order);
//                $order_item->save();
//
//                $this->order_items->push($order_item);
//            }
//
//            $refunded_quantity = $this->getFilteredLineItemsData()
//                ->sum(function($item) {
//                    return static::refundedQuantityForLineItem($this->order_data, $item['id']);
//                });
//
//            \Log::debug('refunds', compact('refunded_quantity')
//                + ['refunds' => array_get($this->order_data, 'refunds')]);
            // If the order is already completely refunded, just cancel it.
//            if ($refunded_quantity >= array_sum($item_quantities)) {
//                $this->cancel(new Carbon());
//            }

            DB::commit();

            $this->imported = true;
        } catch (Exception $e) {
            DB::rollBack();

            $order = null;

            $trace = Util::exceptionArr($e);

            $this->msg('create', compact('trace'), 'emergency');

            if (config('services.shopify.app.throw_processing_exceptions')) {
                throw $e;
            }
        }

        $this->order = $this->order->fresh();
        $this->order_items = $this->order->order_items;

        // White Labeling and Engraving
        event(new Created($this->order));

        return $this->order;
    }

    /**
     * @param array $order_data
     * @param array $attributes
     * @return Customer
     */
    public function fillCustomer(array $order_data, array $attributes = [])
    {
        $store = $this->getStore();
        $customer_model = config('shopify.customers.model');
        $sc_id = array_get($order_data, 'customer.id');
        $email = array_get($order_data, 'email', array_get($order_data, 'customer_email'));

        $c = $customer_model::findByStoreCustomerId($sc_id, $store);

        if (empty($c) && ! empty($email)) {
            $c = $customer_model::findByEmail($email, $store);
        }

        if (empty($c)) {
            $c = new $customer_model;
        }

        $map = config('shopify.customers.map_from_orders');
        $model = $c->exists ? $c : config('shopify.customers.model');
        $mapped_data = $this->util()::mapData($order_data, $map, $model);

        $data = $attributes
            + $mapped_data
            + ['synced_at' => new Carbon('now')];

        return $this->customer->fill($data);
    }

    /**
     * @param array $line_item
     * @param array $attributes
     * @return OrderItem
     */
    protected function fillNewOrderItem(array $line_item, array $attributes = [])
    {
        $variant = $this->getFilteredVariants()[$line_item['variant_id']];

        if (empty($product = $variant->product()->withTrashed()->first())) {
            throw new BadMethodCallException('Product not found');
        }

        $order_item = $this->fillOrderItem(new OrderItem(), $line_item, $attributes);
        $order_item->variant()->associate($variant);
        $order_item->product()->associate($product);
        $order_item->store_product_id = $product->store_product_id;

        return $order_item;
    }

    /**
     * @param OrderItem $oi
     * @param array $line_item
     * @param array $attributes
     * @return OrderItem
     */
    protected function fillOrderItem(OrderItem $oi, array $line_item, array $attributes = [])
    {
        $map = config('shopify.orders.items.map');
        $model = $this->imported ? $oi : config('shopify.customers.model');
        $mapped_data = $this->util()::mapData($line_item, $map, $model);

        $oi->fill($attributes
            + $mapped_data
            + ['synced_at' => new Carbon('now')]);

        $this->applyRefundsTo($oi);

        return $oi;
    }

    /**
     * @param $order_data
     * @return Order
     */
    protected function fillMap(array $order_data)
    {
        $map = config('shopify.orders.map');
        $model = $this->order ?: config('shopify.orders.model');
        $mapped_data = $this->util()::mapData($order_data, $map, $model);

        $data = $mapped_data
            + $this->getStore()->unmorph('store')
            + $this->customer->compact('customer')
            + ['synced_at' => new Carbon('now')];

        return $this->order->fill($data);
    }

    /**
     * @return Carbon|null
     */
    public function getRefundedAt()
    {
        if ($this->order_data['fulfillment_status'] != ShopifyOrder::FINANCIAL_STATUS_REFUNDED) {
            return null;
        }

        // Find the most recent refund date.
        $refunded_at = collect($this->order_data['refunds'])
            ->sortByDesc('create_at')
            ->first();

        return $refunded_at && isset($refunded_at['created_at'])
            ? Carbon::parse($refunded_at['created_at'])
            : null;
    }

    /**
     * @return Customer
     */
    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    /**
     * @return Order|null
     */
    public function getOrder(): ?Order
    {
        return $this->order;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getOrderItems()
    {
        if (! is_null($this->order_items)) {
            return $this->order_items;
        }

        return $this->order_items = $this->order->order_items->keyBy('store_line_item_id');
    }

//    /**
//     * @return float
//     */
//    public function getTotalShipping()
//    {
//        $shipping_lines = isset($this->order_data['shipping_lines'])
//            ? $this->order_data['shipping_lines']
//            : isset($this->order->api_cache['shipping_lines'])
//                ? ($this->order->api_cache['shipping_lines'] ?: [])
//                : [];
//
//        return floatval(array_reduce(
//            $shipping_lines,
//            function($total, $line) {
//                return $total
//                    + (isset($line['price']) ? $line['price'] : 0)
//                    + array_reduce($line['tax_lines'],
//                        function($tax_total, $tax_line) {
//                            return $tax_total
//                                + (isset($tax_line['price']) ? $tax_line['price'] : 0);
//                        },
//                        0);
//            },
//            0));
//    }

    /**
     * @return BaseCollection
     */
    protected function getFilteredOrderItems()
    {
        // Handle any line items that have been refunded.
        return $this->getFilteredLineItemsData()
            ->map(function(array $li) {
                if ($oi = $this->getOrderItems()->get($li['id'])) {
                    return $this->fillOrderItem($oi, $li);
                }

                $order_item = $this->fillNewOrderItem($li);
                $order_item->order()->associate($this->order);

                return $order_item;
            });
    }

    /**
     * Fetch line items from order_data that have variant ids in the DB.
     *
     * Return collection key'd by line item id.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getFilteredLineItemsData()
    {
        if ($this->filtered_line_items_data) {
            return $this->filtered_line_items_data;
        }

        $line_items = $this->order_data['line_items'] ?? [];

        return $this->filtered_line_items_data = collect($line_items)
            ->filter(function(array $li) {
                if ($oi = $this->getOrderItems()->get($li['id'])) {
                    return $this->util()::filterOrderItemUpdate($li, $oi, $oi->variant);
                }

                return $this->util()::filterOrderItemImport($li, $lookup = null);
            })
            ->keyBy('id');
    }

    /**
     * @return Collection
     */
    public function getFilteredVariants()
    {
        // The variants are already cached, send them back
        if (! is_null($this->filtered_variants)) {
            return $this->filtered_variants;
        }

        $ids = $this->getFilteredLineItemsData()
            ->pluck('id', 'variant_id')
            ->keys()
            ->filter()
            ->all();

        $variant_model = config('shopify.products.variants.model');

        // Get them variants from the DB
        $data = $this->filtered_variants = (new $variant_model)
            ->newQuery()
            ->whereIn('store_variant_id', $ids)
            ->get()
            ->keyBy('store_variant_id');

        return $data;
    }

    /**
     * Fetch line items from order_data with no variant id in the DB.
     *
     * Return collection key'd by line item id.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getUnfilteredLineItemsData()
    {
        $line_items = $this->order_data['line_items'] ?? [];

        $ids = $this->getFilteredVariants()->keys();

        return collect($line_items)
            ->filter(function(array $line_item) use ($ids) {
                return ! $ids->has($line_item['variant_id']);
            })
            ->keyBy('id');
    }

    /**
     * @param Store $store
     * @param array $order_data
     * @param Order|null $order
     * @return $this
     */
    protected function init(Store $store, array $order_data = [], Order $order = null)
    {
        $this->order_data = $order_data;
        $order_model = config('shopify.orders.model');

        // SANITY CHECK: Command is often queued, check again if the order exists.
        if (isset($order_data['id']) && empty($order)) {
            if ($existing = $order_model::findByStoreOrderId($order_data['id'], $store)) {
                $this->order = $order = $existing;
            }
        }

        $this->order = $order ?: new $order_model;

        $this->imported = $this->order->exists;
        $this->updated = false;

        if ($this->order->exists) {
            $this->filtered_line_items_data = $this->getFilteredLineItemsData();
            $this->order_items = $this->order->order_items;
            $this->customer = $this->order->customer;
        } else {
            $this->filtered_line_items_data = $this->getFilteredLineItemsData();
            $this->order_items = collect();
            $this->customer = $this->fillCustomer($this->order_data);
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isFiltered()
    {
        return $this->order && $this->imported
            ? $this->util()::filterOrderUpdate($this->order_data, $this->order)
                && $this->getFilteredLineItemsData()->count()
            : $this->util()::filterOrderImport($this->order_data)
                && $this->getFilteredLineItemsData()->count();
    }

    /**
     * @param Store $store
     * @param array $order_data
     * @param Order|null $order
     * @return bool
     */
    public static function filter(Store $store, array $order_data, Order $order = null)
    {
        return (new static($store, $order_data, $order))->isFiltered();
    }

    /**
     * @param array $order_data
     * @param $line_item_id
     * @return int
     */
    public static function refundedQuantityForLineItem(array $order_data, $line_item_id)
    {
        $refunds = $order_data['refunds'] ?: [];

        return array_reduce(
            $refunds,
            function($quantity, $refund) use ($line_item_id) {
                $refund_line_items = isset($refund['refund_line_items'])
                    ? $refund['refund_line_items']
                    : [];

                return $quantity
                    + array_reduce(
                        $refund_line_items,
                        function($line_item_quantity, $refund_item) use ($line_item_id) {
                            return $refund_item['line_item_id'] == $line_item_id
                                ? $line_item_quantity + $refund_item['quantity']
                                : $line_item_quantity;
                        }, 0);
            }, 0);
    }

    /**
     * @param array $order_data
     */
    protected function logTestOrders(array $order_data): void
    {
        if (config('shopify.orders.log_tests')
            && isset($order_data['test'])
            && $order_data['test']
            && ! empty($this->filtered_line_items_data)
            && ! $this->order->exists) {
            $this->msg('test_order', $order_data, 'info');
        }
    }

//    /**
//     * @param array $attributes
//     * @return Order
//     * @throws Exception
//     */
//    public function update(array $product_data, array $attributes =  [])
//    {

//        $this->fill($this->order_data, $attributes);
//
//        $order_items = $this->order_items->keyBy('store_line_item_id');
//        $line_items = collect($this->order_data['line_items'])->keyBy('id');
//
//        /** @var OrderItem $order_item */
//        foreach ($order_items as $li_id => $order_item) {
//            if (isset($line_items[$li_id])) {
//                $this->fillOrderItem($order_item,  $line_items[$li_id], $attributes);
//            }
//        }
//
//        // Flag SHIPPED if fulfilled on Shopify.
//        if (! empty($this->order->fulfillments)
//            && empty($this->order->fulfilled_at)
//            && $this->order->isCompletelyShipped())
//        {
//            $this->order->fulfilled_at = new Carbon();
//            $this->order->status = Order::STATUS_SHIPPED;
//        }
//
//        try {
//            DB::beginTransaction();
//
//            $this->customer->save();
//
//            $this->order->save();
//
//            foreach ($order_items as $order_item) {
//                /** @var OrderItem $order_item */
//                $order_item->save();
//            };
//
//            DB::commit();
//        } catch (Exception $e) {
//            DB::rollBack();
//            $trace = Util::exceptionArr($e);
//
//            $this->msg('update', compact('trace'), 'emergency');
//
//            if (config('services.shopify.app.throw_processing_exceptions')) {
//                throw $e;
//            }
//        }
//
//        $this->order = $this->order->fresh();
//        $this->order_items = $this->order->order_items;
//
//        return $this->order;
//    }

//    /**
//     * @return $this
//     */
//    public function updateRisk()
//    {
//        // Sanity check
//        if (! $this->order->exists) {
//            throw new BadMethodCallException('You must save the order before accessing risk.');
//        }
//
//        $risks = RateLimitedRequest::respond(function() {
//            return $this->getStore()
//                ->apiClient()
//                ->orders()
//                ->risks($this->order->store_order_id);
//        });
//
//        $risks = isset($risks['risks']) ? $risks['risks'] : $risks;
//
//        if (is_array($risks)) {
//            // Default to low
//            $this->order->risk_recommendation = ShopifyOrder::RISK_RECOMMENDATION_LOW;
//
//            foreach ($risks as $risk) {
//                $accessed_risk = array_search($risk['recommendation'], ShopifyOrder::$risk_statuses);
//                $current_risk = array_search($this->order->risk_recommendation, ShopifyOrder::$risk_statuses);
//                if ($accessed_risk > $current_risk) {
//                    $this->order->risk_recommendation = $risk['recommendation'];
//                }
//            }
//
//            $this->order->risks = $risks;
//
//            $this->order->save();
//
//            event(new RiskAccessed($this->order));
//        }
//
//        return $this;
//    }

//    /**
//     * @param array $data
//     * @return $this
//     */
//    public function updateRefunds(array $data)
//    {
//        $order = $this->order;
//
//        if ($order->hasGoneToProduction()) {
//            event(new RefundRefused($order, $data));
//        } elseif ($order->isModifiable()) {
//            $order_items = $order->order_items->keyBy('store_line_item_id');
//            $item_qty = $order->order_items->keyBy('store_line_item_id')
//                ->map(function($oli) { return $oli->quantity; })
//                ->all();
//
//            $refunded_qty = collect($data['refund_line_items'])
//                ->keyBy('line_item_id')
//                ->map(function($li) { return $li['quantity']; })
//                ->all();
//
//            // Set refunded items quantity to 0, append *Refunded to title.
//            foreach ($refunded_qty as $line_item_id => $quantity) {
//                if (isset($order_items[$line_item_id])) {
//                    $order_items[$line_item_id]->quantity -= $quantity;
//                    if ($order_items[$line_item_id]->quantity <= 0) {
//                        $order_items[$line_item_id]->quantity = 0;
//                        if (strpos($order_items[$line_item_id]->name, ' *Partially Refunded')) {
//                            $order_items[$line_item_id]->name = str_replace(' *Partially Refunded', ' *Refunded', $order_items[$line_item_id]->name);
//                            $order_items[$line_item_id]->title = str_replace(' *Partially Refunded', ' *Refunded', $order_items[$line_item_id]->title);
//                        } elseif (strpos($order_items[$line_item_id]->name, ' *Refunded') === false) {
//                            $order_items[$line_item_id]->name .= ' *Refunded';
//                            $order_items[$line_item_id]->title .= ' *Refunded';
//                        }
//                    } elseif (strpos($order_items[$line_item_id]->name, ' *Partially Refunded') === false){
//                        $order_items[$line_item_id]->name .= ' *Partially Refunded';
//                        $order_items[$line_item_id]->title .= ' *Partially Refunded';
//                    }
//                    $order_items[$line_item_id]->save();
//
//                    // Keep track of the total line item quantity refunded.
//                    $item_qty[$line_item_id] -= $quantity;
//                    if ($item_qty[$line_item_id] < 0) {
//                        $item_qty[$line_item_id] = 0;
//                    }
//                }
//            }
//
//            event(new Refunded($order, $data));
//
//            // If the order has been completely refunded, flag it cancelled.
//            if (array_sum($item_qty) <= 0) {
//                $this->cancel(new DateTime());
//            }
//        }
//
//        return $this;
//    }
}
