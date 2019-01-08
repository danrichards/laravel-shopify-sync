<?php

namespace Dan\Shopify\Laravel\Models;

use Dan\Shopify\Laravel\Traits\BelongsToStore;
use Dan\Shopify\Models\Product as ShopifyProduct;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use More\Laravel\Model;
use More\Laravel\Traits\Model\BelongsToUser;

/**
 * Class Product
 *
 * @method static static|Builder forTemplateSuffix(string $template_suffix)
 * @property string $body_html
 * @property Collection $customers
 * @property string $handle
 * @property array $images
 * @property Collection $orders
 * @property Collection $order_items
 * @property Store $store
 * @property string $store_product_id
 * @property string $template_suffix
 * @property string $title
 * @property \App\User $user
 * @property Collection $variants
 */
class Product extends Model
{
    use BelongsToUser, BelongsToStore, SoftDeletes; //Taggable;

    /** @var string $table */
    protected $table = 'products';

    /** @var array $guarded */
    protected $guarded = ['id'];

    /** @var array $dates */
    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
        'published_at',
        'store_created_at',
        'store_updated_at',
        'synced_at',
    ];

    /** @var array $casts */
    protected $casts = [
        'images' => 'array',
        'image' => 'array',
        'options' => 'array',
        'tags' => 'array',
        'metafields' => 'array',
//        'store_created_at' => 'datetime',
//        'store_updated_at' => 'datetime',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function orders()
    {
        return $this->hasManyThrough(Order::class,OrderItem::class, 'product_id', 'id', 'id','order_id')
            ->distinct('orders.id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function order_items()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function store()
    {
        return $this->morphTo('store');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function customers()
    {
        return (new Customer)->newQuery()::select('customers.*')
            ->join('order_items', 'order_items.product_id', '=', DB::raw($this->getKey()));
    }

    /**
     * @return Collection
     */
    public function getCustomersAttribute()
    {
        return $this->customers()->get();
    }

    /**
     * Find a listing for a specific store.
     *
     * @param int $product_id
     * @param Store $store
     * @return Product|null
     */
    public static function findByStoreProductId($product_id, Store $store)
    {
        return Product::whereMorph($store, 'store')
            ->where('store_product_id', $product_id)
            ->first();
    }

    /**
     * @param ShopifyProduct $shopify_product
     * @param array $attributes
     * @return Product
     */
    public static function createForShopifyProduct(Store $store, ShopifyProduct $shopify_product, array $attributes = [])
    {
        $product = static::fillNewForShopifyProduct($store, $shopify_product, $attributes);

        $product->save();

        return $product;
    }

    /**
     * @param ShopifyProduct $shopify_product
     * @param array $attributes
     * @return Product
     */
    public static function fillNewForShopifyProduct(Store $store, ShopifyProduct $shopify_product, array $attributes = [])
    {
        return (new static)->fillForShopifyProduct($store, $shopify_product, $attributes);
    }

    /**
     * @param Store $store
     * @param ShopifyProduct $shopify_product
     * @param array $attributes
     * @return $this
     */
    public function fillForShopifyProduct(Store $store, ShopifyProduct $shopify_product, array $attributes = [])
    {
        $data = $store->unmorph('store');

        $data['user_id'] = $store->user_id;
        $data['store_product_id'] = $shopify_product->getKey();

        $data['api_cache'] = $shopify_product->getAttributes();
        $data['api_cached_at'] = now();

        $update = $shopify_product->getAttributes();

        $data += array_intersect_key($update, array_fill_keys($updatable, null));

        return $this->fill($attributes + $data);
    }

    /**
     * DOES NOT UPDATE VARIANT DATA
     *
     * @param ShopifyProduct $shopify_product
     * @param array $attributes
     * @return bool
     */
    public function updateForShopifyProduct(ShopifyProduct $shopify_product, array $attributes = [])
    {
        $attributes += ['synced_at' => now()];

        $this->fillForShopifyProduct($this->store, $shopify_product, $attributes);

        return $this->save();
    }

    /**
     * @param ShopifyProduct $shopify_product
     * @return array
     * @throws Exception
     */
    public function updateVariantsForShopifyProduct(ShopifyProduct $shopify_product)
    {
        $store = $this->store;

        $live_ids = collect($shopify_product->variants)->pluck('id')->values()->all();
        $db_ids = $this->variants()->pluck('store_variant_id')->values()->all();
        $delete_ids = array_diff($db_ids, $live_ids);

        Variant::whereIn('store_variant_id', $delete_ids)
            ->get()
            ->each(function(Variant $v) {
                $v->delete();
            });

        foreach ($shopify_product->variants as $variant_arr) {
            $variant_id = $variant_arr['id'];
            /** @var Variant $variant */
            $variant = Variant::findByStoreProductIdVariantId($shopify_product->getKey(), $variant_id, $store);
            if ($variant) {
                $variant->updateForShopifyProduct($shopify_product);
                $updated_ids[] = $variant_id;
            } else {
                $delete_ids[] = $variant_id;
            }
        }

        return compact('delete_ids', 'updated_ids');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder|\App\Model $query
     * @param string $template_suffix
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForTemplateSuffix($query, $template_suffix)
    {
        return $query->where('template_suffix', $template_suffix);
    }

}
