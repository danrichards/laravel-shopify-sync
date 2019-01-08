<?php

namespace Dan\Shopify\Laravel\Models;

use Carbon\Carbon;
use Crypt;
use Dan\Shopify\HasShopifyClientInterface;
use Dan\Shopify\Shopify;
use Dan\Shopify\Util;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use More\Laravel\Model;
use More\Laravel\Traits\HasLocales;
use More\Laravel\Traits\Model\BelongsToUser;

/**
 * Class Store
 *
 * @see Traits for property references.
 *
 * @property string $address1
 * @property string $address2
 * @property bool|null $checkout_api_supported
 * @property string $city
 * @property string $country
 * @property string $country_code
 * @property string $country_name
 * @property bool|null $county_taxes
 * @property string $currency
 * @property string $customer_email
 * @property Carbon $created_at
 * @property Carbon $deleted_at
 * @property string $domain
 * @property bool|null $eligible_for_payments
 * @property bool|null $eligible_for_card_reader_giveaway
 * @property string $email
 * @property array $enabled_presentment_currencies
 * @property bool|null $finances
 * @property bool|null $force_ssl
 * @property string $google_apps_domain
 * @property bool|null $google_apps_login_enabled
 * @property string $has_discounts
 * @property string $has_gift_cards
 * @property bool|null $has_storefront
 * @property string $iana_timezone
 * @property Carbon $last_login_at
 * @property Carbon $last_webhook_at
 * @property Carbon $last_call_at
 * @property Carbon $last_product_import_at
 * @property Carbon $last_order_import_at
 * @property string $latitude
 * @property string $longitude
 * @property string $money_format
 * @property string $money_in_emails_format
 * @property string $money_with_currency_in_emails_format
 * @property string $money_with_currency_format
 * @property string $myshopify_domain
 * @property bool|null $multi_location_enabled
 * @property string $name
 * @property int $order_count
 * @property bool|null $password_enabled
 * @property string $phone
 * @property string $plan_name
 * @property string $plan_display_name
 * @property bool|null $pre_launch_enabled
 * @property string $primary_location_id
 * @property string $primary_locale
 * @property int $product_count
 * @property string $province
 * @property string $province_code
 * @property bool|null $requires_extra_payments_agreement
 * @property array $scopes
 * @property string $shop
 * @property string $shop_owner
 * @property Carbon $store_created_at
 * @property Carbon $store_updated_at
 * @property bool|null $taxes_included
 * @property bool|null $tax_shipping
 * @property string $timezone
 * @property string $token
 * @property Carbon $uninstalled_at
 * @property string $uninstall_code
 * @property Carbon $updated_at
 * @property int $user_id
 * @property array $webhooks
 * @property string $weight_unit
 * @property string $zip
 */
class Store extends Model implements HasShopifyClientInterface
{
    use BelongsToUser,
        HasLocales,
        Notifiable,
        SoftDeletes;

    /** @var string $table */
    protected $table = 'stores';

    /** @var array $guarded */
    protected $guarded = ['id'];

    /** @var string $guard_name */
    protected $guard_name = 'web';

    /** @var array $dates */
    protected $dates = [
        'created_at',
        'deleted_at',
        'last_call_at',
        'last_customer_import_at',
        'last_customer_update_at',
        'last_login_at',
        'last_order_import_at',
        'last_order_update_at',
        'last_product_import_at',
        'last_product_update_at',
        'last_webhook_at',
        'store_created_at',
        'store_updated_at',
        'uninstalled_at',
        'updated_at',
    ];

    /** @var array $casts */
    protected $casts = [
        'scopes' => 'array',
        'webhooks' => 'array',
        'enabled_presentment_currencies' => 'array',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|Product
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'store_id')
            ->whereMorphedBy(get_class($this), 'store');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function variants()
    {
        return $this->hasManyThrough(Variant::class, Product::class, 'store_id');
    }

    /**
     * @return Builder
     */
    public function customers()
    {
        return $this->belongsToMany(Customer::class, 'orders',  'store_id', 'customer_id')
            ->whereMorphedBy(get_class($this), 'store')
            ->groupBy('customers.id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'store_id')
            ->whereMorphedBy(get_class($this), 'store');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function order_items()
    {
        return $this->hasManyThrough(OrderItem::class, Order::class, 'store_id')
            ->whereMorphedBy(get_class($this), 'store');
    }

    /**
     * @param string $store_order_id
     * @return Order|null
     */
    public function findOrderByStoreId($store_order_id)
    {
        return Order::where('store_order_id', $store_order_id)
            ->whereMorph($this, 'store')
            ->first();
    }

    /**
     * @param string $store_line_item_id
     * @return Order|null
     */
    public function findOrderLineItemByStoreId($store_line_item_id)
    {
        return OrderItem::select('order_items.*')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('store_line_item_id', $store_line_item_id)
            ->whereMorph($this)
            ->first();
    }

    /**
     * @param $value
     * @return string
     */
    public function getTokenAttribute($value)
    {
        return Crypt::decrypt($value);
    }

    /**
     * Store the token securely
     *
     * @param $value
     */
    public function setTokenAttribute($value)
    {
        $this->attributes['token'] = Crypt::encrypt($value);
    }

    /** @return string */
    public function getShop()
    {
        return $this->shop;
    }

    /** @return string */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @return Shopify
     */
    public function getApiClient()
    {
        return new Shopify($this->getShop(), $this->getToken());
    }

    /**
     * @param $hook
     * @return $this
     */
    public function addWebhook($hook)
    {
        array_push($this->webhooks, trim($hook, ' /'));
        return $this;
    }

    /**
     * @param $hook
     * @return boolean
     */
    public function hasWebhook($hook)
    {
        $hook = trim($hook, ' /');
        return in_array($hook, $this->webhooks);
    }

    /**
     * @param Request $request
     * @return bool
     */
    public function authorizes(Request $request)
    {
        return static::validAppHmac($request);
    }

    /**
     * @param Request $request
     * @param string|null $secret
     * @return bool
     */
    public static function validAppHmac(Request $request, $secret = null)
    {
        $secret = $secret ?: config('services.shopify.app.secret');
        $data = array_filter($request->all(['code', 'locale', 'protocol', 'shop', 'state', 'timestamp']));
        $hmac = $request->get('hmac');

        return Util::validAppHmac($hmac, $secret, $data);
    }

    /**
     * @param $myshopify_domain
     * @param $secret
     * @param $timestamp
     * @return string
     */
    public static function makeApiAuthToken($myshopify_domain, $secret, $timestamp)
    {
        $myshopify_domain = static::normalizeDomain($myshopify_domain);
        $api_auth_token = base64_encode(hash('sha256',
            sprintf("SOAuth %s:%s:%s",
                $myshopify_domain,
                $secret,
                $timestamp
            )));

        return $api_auth_token;
    }

    /**
     * @param string $myshopify_domain
     * @return string
     */
    public static function normalizeDomain($myshopify_domain)
    {
        $myshopify_domain = strtolower($myshopify_domain);
        $myshopify_domain = str_replace('.myshopify.com', '', $myshopify_domain);

        return sprintf("%s.myshopify.com", $myshopify_domain);
    }

    /**
     * @param array|string $scopes
     * @return bool
     */
    public function hasScopes($scopes)
    {
        $scopes = (array) $scopes;

        return count(array_intersect($this->scopes, $scopes))
            == count($scopes);
    }

    /**
     * @return bool
     */
    public function isInstalled()
    {
        return is_null($this->uninstalled_at);
    }

    /**
     * @return bool
     */
    public function isUninstalled()
    {
        return ! $this->isInstalled();
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeForInstalled($query)
    {
        return $query->whereNull("{$this->getTable()}.uninstalled_at");
    }

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeForUninstalled($query)
    {
        return $query->whereNotNull("{$this->getTable()}.uninstalled_at");
    }
}
