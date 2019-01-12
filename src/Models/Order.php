<?php

namespace Dan\Shopify\Laravel\Models;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model as BaseModel;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use More\Laravel\Model;
use More\Laravel\Traits\Model\BelongsToUser;

/**
 * Class Order
 *
 * @property Customer $customer
 * @property array $fulfillments
 * @property Collection $order_items
 * @property Collection $products
 * @property array $refunds
 * @property string $store_order_id
 * @property string $store_location_id
 * @property Collection $variants
 * @property Carbon $fulfilled_at
 * @property Carbon $closed_at
 * @property Carbon $cancelled_at
 * @property Carbon $refunded_at
 * @property Carbon $process_at
 * @property Carbon $store_created_at
 * @property Carbon $store_updated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon $deleted_at
 * @method static static|Builder forFinancialStatus($status)
 * @method static static|Builder forFulfillmentStatus($status)
 * @method static static|Builder forRiskAssessment(Store|null $store)
 * @method static static|Builder forRiskRecommendation($status)
 * @method static static|Builder forUnfulfilled()
 */
class Order extends Model
{
    use BelongsToUser,
        BelongsToStore,
        Notifiable,
        SoftDeletes;

    /** @var string $table*/
    protected $table = 'orders';

    /** @var array $fillable */
    protected $guarded = ['id'];

    /** @var array $dates */
    protected $dates = [
        'cancelled_at',
        'closed_at',
        'created_at',
        'deleted_at',
        'fulfilled_at',
        'processed_at',
        'refunded_at',
        'store_created_at',
        'store_updated_at',
        'synced_at',
        'updated_at',
        'webhook_error_at',
    ];

    /** @var array $casts */
    protected $casts = [
        'billing_address' => 'array',
        'client_details' => 'array',
        'discount_applications' => 'array',
        'discount_codes' => 'array',
        'fulfillments' => 'array',
        'note_attributes' => 'array',
        'payment_details' => 'array',
        'payment_gateway_names' => 'array',
        'refunds' => 'array',
        'risks' => 'array',
        'shipping_address' => 'array',
        'shipping_lines' => 'array',
        'subtotal_price_set' => 'array',
        'tax_lines' => 'array',
        'total_discounts_set' => 'array',
        'total_line_items_price_set' => 'array',
        'total_price_set' => 'array',
        'total_shipping_price_set' => 'array',
        'total_tax_set' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(config('shopify.customers.model'))->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function order_items()
    {
        return $this->hasMany(config('shopify.orders.items.model'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function products()
    {
        $order_item_model = config('shopify.orders.items.model');
        $order_item_table = (new $order_item_model)->getTable();
        $product_model = config('shopify.products.model');
        $product_fk = (new $product_model)->getForeignKey();

        return $this->hasManyThrough($product_model, $order_item_model, null, 'id', null, $product_fk)
            ->where("{$order_item_table}.{$product_fk}", '>', 0)
            ->whereNotNull("{$order_item_table}.{$product_fk}")
            ->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function variants()
    {
        $order_item_model = config('shopify.orders.items.model');
        $order_item_table = (new $order_item_model)->getTable();
        $product_model = config('shopify.products.model');
        $product_fk = (new $product_model)->getForeignKey();
        $variant_model = config('shopify.products.variants.model');
        $variant_fk = (new $variant_model)->getForeignKey();

        return $this->hasManyThrough($variant_model, $order_item_model, null, 'id', null, $variant_fk)
            ->where("{$order_item_table}.{$product_fk}", '>', 0)
            ->whereNotNull("{$order_item_table}.{$product_fk}")
            ->withTrashed();
    }

    /**
     * @param $shopify_order_id
     * @param Store $store
     * @return Order|null
     */
    public static function findByStoreOrderId($shopify_order_id, $store = null)
    {
        return static::where('store_order_id', $shopify_order_id)
            ->when($store, function(Builder $q, $s) {
                $q->whereMorph($s);
            })
            ->first();
    }

    /**
     * @param $number
     * @param Store $store
     * @return Order|null
     */
    public static function findByNumber($number, $store)
    {
        return static::where('number', $number)
            ->whereMorph($store, 'store')
            ->first();
    }

    /**
     * @param $query
     * @return Builder
     */
    public function scopeForFulfilled($query)
    {
        $table = (new static)->getTable();

        return $query->whereNotNull("{$table}.fulfilled_at");
    }

    /**
     * @param $query
     * @return Builder
     */
    public function scopeForUnfulfilled($query)
    {
        $table = (new static)->getTable();

        return $query->whereNull("{$table}.fulfilled_at");
    }

    /**
     * @param $query
     * @param string|array $status
     * @return Builder
     */
    public function scopeForFulfillmentStatus($query, $status)
    {
        $table = (new static)->getTable();

        return $query->whereIn("{$table}.fulfillment_status", (array) $status);
    }

    /**
     * @param $query
     * @param string|array $status
     * @return Builder
     */
    public function scopeForFinancialStatus($query, $status)
    {
        $table = (new static)->getTable();

        return $query->whereIn("{$table}.financial_status", (array) $status);
    }

//    /**
//     * @param Builder $query
//     * @param Store $store
//     * @return Builder
//     */
//    public function scopeForRiskAssessment($query, Store $store = null)
//    {
//        return $query->whereNull('risk_recommendation')
//            ->when($store, function(Builder $q, $s) {
//                /** @var Store $q */
//                $q->whereMorph($s, 'store');
//            });
//    }
//
//    /**
//     * @param Builder $query
//     * @param string|array $risk_recommendation
//     * @return Builder
//     */
//    public function scopeForRiskRecommendation($query, $risk_recommendation)
//    {
//        return $query->whereIn('orders.risk_recommendation', (array) $risk_recommendation);
//    }
//
//    /**
//     * @param string $risk
//     * @return bool
//     */
//    public function isRiskierThan($risk)
//    {
//        // Risk hasn't been accessed yet, treat as risky.
//        if (empty($this->risk_recommendation)) {
//            return true;
//        }
//
//        // If the risk level for the order meets the user's preference.
//        $risk_level = array_search($this->risk_recommendation, ShopifyOrder::$risk_statuses);
//
//        return $risk_level > $risk;
//    }

    /**
     * @return bool|null
     */
    public function forceDelete()
    {
        // Tear down relations first!
        $this->order_items()
            ->withTrashed()
            ->get()
            ->each(function(BaseModel $order_item) {
                $order_item->forceDelete();
            });

        return $this->forceDelete();
    }
}
