<?php

namespace Dan\Shopify\Laravel\Models;

use Dan\Shopify\Models\Product as ShopifyProduct;
use Dan\Shopify\Models\Variant as ShopifyVariant;
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
     * @param Store $store
     * @param ShopifyVariant $shopify_variant
     * @param array $attributes
     * @return Variant
     */
    public static function createForShopifyVariant(Store $store, ShopifyVariant $shopify_variant, array $attributes = [])
    {
        $variant = static::fillForShopifyVariant($store, $shopify_variant, $attributes);
        $variant->save();
        return $variant;
    }

    /**
     * @param Store $store
     * @param ShopifyVariant $shopify_variant
     * @param array $attributes
     * @return Variant
     */
    public static function fillForShopifyVariant(Store $store, ShopifyVariant $shopify_variant, array $attributes = [])
    {
        $data = $shopify_variant->getAttributes();
        $product = Product::findByStoreProductId($shopify_variant->product_id, $store);

        $shopify_fields = array_flip(Variant::$shopify_fields);

        $shopify_data = $attributes + array_intersect_key($data, $shopify_fields)
            + [
                'store_variant_id' => $data['id'],
                'store_product_id' => $data['product_id'],
                'product_id' => $product->getKey()
            ];

        return new Variant($shopify_data);
    }

    /**
     * @param ShopifyProduct $shopify_product
     * @param array $attributes
     * @return bool
     */
    public function updateForShopifyProduct(ShopifyProduct $shopify_product, array $attributes = [])
    {
        $attributes += ['synced_at' => now()];

        $update = collect($shopify_product->variants)->first(function($v) {
            return $v['id'] == $this->store_variant_id;
        });
        $updatable = array_diff(static::$shopify_fields, static::$shopify_ignore_api_on_update);
        $data = array_intersect_key($update, array_fill_keys($updatable, null));

        $this->update($data + $attributes);

        return true;
    }

    /**
     * @param Store $store
     * @param ShopifyVariant $shopify_variant
     * @return Variant|null
     */
    public static function findByShopifyVariant(Store $store, ShopifyVariant $shopify_variant)
    {
        return self::findByStoreProductIdVariantId(
            $store_product_id = $shopify_variant->product_id,
            $store_variant_id = $shopify_variant->getKey(),
            $store
        );
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
            ->whereMorph($store, 'store')
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
