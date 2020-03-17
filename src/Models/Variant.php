<?php

namespace Dan\Shopify\Laravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use More\Laravel\Model;

/**
 * Class Variant
 *
 * @method static forEngraved(string $group_by = 'variants.id') \Illuminate\Database\Eloquent\Builder|Variant
 * @method static forNotEngraved(string $group_by = 'variants.id') \Illuminate\Database\Eloquent\Builder|Variant
 * @method static forBuyerUpload($value = 1) \Illuminate\Database\Eloquent\Builder|Variant
 * @method static forNotBuyerUpload() \Illuminate\Database\Eloquent\Builder|Variant
 * @property Store $store
 * @property Product $product
 * @property Collection $skus
 * @property int $sku_id
 * @property array $metafields
 * @property string $store_product_id
 * @property string $store_variant_id
 * @property string $option1
 * @property string $option2
 * @property float $price
 * @property float $compare_at_price
 * @property int $position
 * @property int $product_id
 * @property string title
 */
class Variant extends Model
{
    use SoftDeletes;

    /** @var array $shopify_fields */
    public static $shopify_fields = [
        'title',
        'price',
        'sku',
        'position',
        'grams',
        'compare_at_price',
        'option1',
        'option2',
        'option3',
        'taxable',
        'barcode',
        'image_id',
        'weight',
        'weight_unit',
        'metafields',
    ];

    /** @var string $table */
    protected $table = 'variants';

    /** @var array $guarded */
    protected $guarded = ['id'];

    /** @var array $casts */
    protected $casts = [
        'api_cache' => 'array',
        'metafields' => 'array',
//        'store_created_at' => 'datetime',
//        'store_updated_at' => 'datetime',
    ];

    /** @var array $dates */
    protected $dates = [
        'created_at',
        'deleted_at',
        'store_updated_at',
        'store_created_at',
        'synced_at',
        'updated_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function orders()
    {
        return $this->hasManyThrough(Order::class, OrderItem::class);
    }

    /**
     * @param int $store_product_id
     * @param int $store_variant_id
     * @param Store|null $store
     * @return Variant|null
     */
    public static function findByStoreProductIdVariantId($store_product_id, $store_variant_id, Store $store = null)
    {
        return static::select(['variants.*'])
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->when($store, function(Builder $q, $s) {
                $q->whereMorph($s, 'store');
            })
            ->where('variants.store_product_id', $store_product_id)
            ->where('variants.store_variant_id', $store_variant_id)
            ->first();
    }

    /**
     * @param int $store_variant_id
     * @param Store|null $store
     * @param bool $with_trashed
     * @return Variant|null
     */
    public static function findByStoreVariantId($store_variant_id, Store $store = null, $with_trashed = false)
    {
        return static::select(['variants.*'])
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->where('variants.store_variant_id', $store_variant_id)
            ->when($store, function(Builder $q, $s) {
                $q->whereMorph($s, 'store');
            })
            ->when($with_trashed, function(Builder $q) {
                return $q->withTrashed()->first();
            })
            ->first();
    }
}
