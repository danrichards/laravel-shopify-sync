<?php

namespace Dan\Shopify\Laravel\Models;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Traits\BelongsToStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\SoftDeletes;
use More\Laravel\Model;
use More\Laravel\Traits\Model\User\AbbreviatesNames;

/**
 * Class Customer
 *
 * @method static forMarketing() \Illuminate\Database\Eloquent\Builder
 * @property string $address1
 * @property string $address2
 * @property string $city
 * @property string $company
 * @property string $country
 * @property string $country_name
 * @property string $email
 * @property string $first_name
 * @property string $last_name
 * @property string $note
 * @property Collection $orders
 * @property Collection $order_items
 * @property string $phone
 * @property string $province_code
 * @property Store $store
 * @property Carbon $store_created_at
 * @property string $store_customer_id
 * @property string $store_last_order_id
 * @property Carbon $store_updated_at
 * @property string $zip
 */
class Customer extends Model
{
    use AbbreviatesNames, BelongsToStore, SoftDeletes;

    /** @var string $table */
    protected $table = 'customers';

    /** @var array $guarded */
    protected $guarded = ['id'];

    /** @var array $dates */
    protected $dates = [
        'created_at',
        'deleted_at',
        'synced_at',
        'updated_at',
    ];

    /** @var array $casts */
    protected $casts = [
        'default_address' => 'array',
        'tags' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function store()
    {
        return $this->morphTo();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        $customer_model = config('shopify.customers.model');
        $customer_fk = (new $customer_model)->getForeignKey();

        return $this->hasMany(config('shopify.orders.model'), $customer_fk, 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function order_items()
    {
        $order_model = config('shopify.orders.model');
        $order_item_model = config('shopify.orders.items.model');

        return $this->hasManyThrough($order_item_model, $order_model);
    }

    /**
     * @param string $email
     * @param Store|null $store
     * @return static|null
     */
    public static function findByEmail($email, $store = null)
    {
        return static::where('email', $email)
            ->when($store, function(Builder $q, $s) {
                $q->whereMorph($s, 'store');
            })
            ->first();
    }

    /**
     * @param string $email
     * @param Store|null $store
     * @return static|null
     */
    public static function findByStoreCustomerId($email, $store = null)
    {
        return static::where('store_customer_id', $email)
            ->when($store, function(Builder $q, $s) {
                $q->whereMorph($s, 'store');
            })
            ->first();
    }

    /**
     * @param Store|null $store
     * @return static|null
     */
    public static function findNullEmail($store = null)
    {
        return static::whereNull('email')
            ->when($store, function(Builder $q, $s) {
                $q->whereMorph($s, 'store');
            })
            ->first();
    }

    /**
     * @param $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForMarketing($query)
    {
        return $query->where('accepts_marketing', 1);
    }

    /**
     * @return string
     */
    public function getStateAttribute()
    {
        return $this->attributes['province_code'];
    }
}
