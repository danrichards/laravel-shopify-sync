<?php

namespace Dan\Shopify\Laravel\Models;

use Dan\Shopify\Laravel\Support\Util;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use More\Laravel\Model;
use Dan\Shopify\Models\Order as ShopifyOrder;

/**
 * Class OrderItem
 *
 * @method static Builder|static forFulfilled()
 * @method static Builder|static forStore(Store $store)
 * @method static Builder|static forUnfulfilled()
 * @property Order $order
 * @property int $order_id
 * @property Product $product
 * @property int $product_id
 * @property array $properties
 * @property int $quantity
 * @property string $store_product_id
 * @property string $store_variant_id
 * @property string $store_line_item_id
 * @property string $title
 * @property Variant $variant
 * @property int $variant_id
 */
class OrderItem extends Model
{
    use SoftDeletes;

    /** @var string $table */
    protected $table = 'order_items';
    
    /** @var array $guarded */
    protected $guarded = ['id'];

    /** @var array $dates */
    protected $dates = [
        'created_at',
        'deleted_at',
        'fulfilled_at',
        'synced_at',
        'updated_at',
    ];

    /** @var array $casts */
    protected $casts = [
        'discount_allocations' => 'array',
        'origin_location' => 'array',
        'price_set' => 'array',
        'properties' => 'array',
        'tax_lines' => 'array',
        'total_price_set' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        $order_model = config('shopify.orders.model');
        return $this->belongsTo($order_model);
    }

    /**
     * OrderItem must always be able to fetch it's product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        $product_model = config('shopify.products.model');
        return $this->belongsTo($product_model)->withTrashed();
    }

    /**
     * OrderItem must always be able to fetch it's variant.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function variant()
    {
        $variant_model = config('shopify.products.variants.model');
        return $this->belongsTo($variant_model)->withTrashed();
    }

    /**
     * @param $store_line_item_id
     * @return OrderItem|null
     */
    public static function findByStoreLineItemId($store_line_item_id)
    {
        return static::where('order_items.store_line_item_id', $store_line_item_id)->first();
    }

    /**
     * @param Store $store
     * @param $query
     * @return Builder|static
     */
    public function scopeForStore($query, Store $store)
    {
        return $query->select('order_items.*')
            ->joinOnce('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereMorph($store, 'store');
    }

    /**
     * @param $query
     * @return Builder|static
     */
    public function scopeForFulfilled($query)
    {
        return $query->whereNotNull('order_items.fulfilled_at');
    }

    /**
     * @param $query
     * @return Builder|static
     */
    public function scopeForUnfulfilled($query)
    {
        return $query->whereNull('order_items.fulfilled_at');
    }

    /**
     * @param ShopifyOrder $order
     * @return int
     */
    public function getRefundedQuantity(ShopifyOrder $order)
    {
        $refunds = $order['refunds'] ?? [];

        return array_reduce(
            $refunds,
            function($quantity, $refund) {
                $refund_line_items = isset($refund['refund_line_items'])
                    ? $refund['refund_line_items']
                    : [];
                return $quantity
                    + array_reduce(
                        $refund_line_items,
                        function($line_item_quantity, $refund_item) {
                            return $refund_item['line_item_id'] == $this->store_line_item_id
                                ? $line_item_quantity + $refund_item['quantity']
                                : $line_item_quantity;
                        }, 0);
            }, 0);
    }

    /**
     * It's preferential to always be dealing with an array.
     *
     * @return array
     */
    public function getPropertiesAttribute()
    {
        return json_decode($this->attributes['properties'] ?? '[]', $assoc = true) ?: [];
    }

    /**
     * @param string $name
     * @return string
     */
    public function getPropertyByName($name)
    {
        $prop = collect($this->properties)
            ->first(function ($p) use ($name){
                return $p['name'] == $name;
            });

        return $prop ? array_get($prop, 'value') : null;
    }

    /**
     * @return array
     */
    public function getPropertiesDictionaryAttribute()
    {
        return Util::nameValuesToDictionary($this->properties);
    }
}
