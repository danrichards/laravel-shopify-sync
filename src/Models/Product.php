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
    public static function findByStoreProductId($product_id, Store $store = null)
    {
        return Product::where('store_product_id', $product_id)
            ->when($store, function(Builder $q, $s) {
                $q->whereMorph($s, 'store');
            })
            ->first();
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
