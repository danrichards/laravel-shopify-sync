<?php

use Dan\Shopify\Shopify;

return [
    'app' => [
        'scopes' => [
            Shopify::SCOPE_WRITE_PRODUCTS,
            Shopify::SCOPE_WRITE_ORDERS,
            Shopify::SCOPE_WRITE_FULFILLMENTS,
            Shopify::SCOPE_WRITE_SHIPPING,
            Shopify::SCOPE_WRITE_CONTENT,
            Shopify::SCOPE_WRITE_THEMES,
            Shopify::SCOPE_WRITE_SCRIPT_TAGS,
            Shopify::SCOPE_READ_ANALYTICS,
            Shopify::SCOPE_READ_ORDERS_ALL,
        ],
        'webhooks' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stores settings
    |--------------------------------------------------------------------------
    |
    | Settings for your Store models
    |
    */
    'stores' => [
        'model' => \Dan\Shopify\Laravel\Models\Store::class,
        'map' => [
            'primary_location_id' => 'store_primary_location_id',
            'name' => 'name',
            'shop_owner' => 'shop_owner',
            'email' => 'email',
            'customer_email' => 'customer_email',
            'domain' => 'domain',
            'myshopify_domain' => 'myshopify_domain',
            'address1' => 'address1',
            'address2' => 'address2',
            'city' => 'city',
            'zip' => 'zip',
            'province' => 'province',
            'province_code' => 'province_code',
            'country' => 'country',
            'country_code' => 'country_code',
            'country_name' => 'country_name',
            'source' => 'source',
            'phone' => 'phone',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
            'primary_locale' => 'primary_locale',
            'timezone' => 'timezone',
            'iana_timezone' => 'iana_timezone',
            'currency' => 'currency',
            'money_format' => 'money_format',
            'money_in_emails_format' => 'money_in_emails_format',
            'money_with_currency_in_emails_format' => 'money_with_currency_in_emails_format',
            'money_with_currency_format' => 'money_with_currency_format',
            'weight_unit' => 'weight_unit',
            'plan_name' => 'plan_name',
            'plan_display_name' => 'plan_display_name',
            'has_discounts' => 'has_discounts',
            'has_gift_cards' => 'has_gift_cards',
            'has_storefront' => 'has_storefront',
            'google_apps_domain' => 'google_apps_domain',
            'google_apps_login_enabled' => 'google_apps_login_enabled',
            'eligible_for_payments' => 'eligible_for_payments',
            'eligible_for_card_reader_giveaway' => 'eligible_for_card_reader_giveaway',
            'finances' => 'finances',
            'checkout_api_supported' => 'checkout_api_supported',
            'multi_location_enabled' => 'multi_location_enabled',
            'force_ssl' => 'force_ssl',
            'pre_launch_enabled' => 'pre_launch_enabled',
            'requires_extra_payments_agreement' => 'requires_extra_payments_agreement',
            'password_enabled' => 'password_enabled',
            'enabled_presentment_currencies' => 'enabled_presentment_currencies',
            'shop' => 'shop',
            'taxes_included' => 'taxes_included',
            'tax_shipping' => 'tax_shipping',
            'county_taxes' => 'county_taxes',
            'setup_required' => 'setup_required',
            'created_at' => 'shop_created_at',
            'updated_at' => 'shop_updated_at',
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Customers settings
    |--------------------------------------------------------------------------
    |
    | Settings for your Customer models
    |
    */
    'customers' => [
        'model' => \Dan\Shopify\Laravel\Models\Customer::class,
        'map_from_orders' => [
            'customer.store_customer_id' => 'store_customer_id',
            'customer.store_last_order_id' => 'store_last_order_id',
            'customer.admin_graphql_api_id' => 'admin_graphql_api_id',
            'customer.email' => 'email',
            'customer.first_name' => 'first_name',
            'customer.last_name' => 'last_name',
            'customer.default_address.company' => 'company',
            'phone' => 'phone',
            'customer.default_address.address1' => 'address1',
            'customer.default_address.address2' => 'address2',
            'customer.default_address.city' => 'city',
            'customer.default_address.zip' => 'zip',
            'customer.default_address.province_code' => 'province_code',
            'customer.default_address.country' => 'country',
            'customer.default_address.country_code' => 'country_code',
            'customer.default_address.country_name' => 'country_name',
            'customer.locale' => 'locale',
            'customer.accepts_marketing' => 'accepts_marketing',
            'customer.note' => 'note',
            'customer.tags' => 'tags',
            'customer.default_address' => 'default_address',
            'customer.currency' => 'currency',
            'customer.orders_count' => 'orders_count',
            'customer.total_spent' => 'total_spent',
            'customer.last_order_name' => 'last_order_name',
            'customer.tax_exempt' => 'tax_exempt',
            'customer.verified_email' => 'verified_email',
            'customer.multipass_identifier' => 'multipass_identifier',
            'customer.store_created_at' => 'store_created_at',
            'customer.store_updated_at' => 'store_updated_at',
        ],
        'import_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyCustomerImport',
        'update_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyCustomerUpdate',
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders settings
    |--------------------------------------------------------------------------
    |
    | Settings for your Order models
    |
    */
    'orders' => [
        'model' => \Dan\Shopify\Laravel\Models\Order::class,
        'map' => [
            'id' => 'store_order_id',
            'user_id' => 'store_user_id',
            'app_id' => 'store_app_id',
            'location_id' => 'store_location_id',
            'number' => 'number',
            'name' => 'name',
            'admin_graphql_api_id' => 'admin_graphql_api_id',
            'test' => 'test',
            'email' => 'email',
            'contact_email' => 'contact_email',
            'checkout_id' => 'checkout_id',
            'checkout_token' => 'checkout_token',
            'cart_token' => 'cart_token',
            'token' => 'token',
            'order_status_url' => 'order_status_url',

            // Save and flatten the shipping address
            'shipping_address' => 'shipping_address',
            'shipping_address.shipping_first_name' => 'shipping_first_name',
            'shipping_address.shipping_last_name' => 'shipping_last_name',
            'shipping_address.shipping_name' => 'shipping_name',
            'shipping_address.shipping_phone' => 'shipping_phone',
            'shipping_address.shipping_company' => 'shipping_company',
            'shipping_address.shipping_address1' => 'shipping_address1',
            'shipping_address.shipping_address2' => 'shipping_address2',
            'shipping_address.shipping_city' => 'shipping_city',
            'shipping_address.shipping_province' => 'shipping_province',
            'shipping_address.shipping_province_code' => 'shipping_province_code',
            'shipping_address.shipping_zip' => 'shipping_zip',
            'shipping_address.shipping_country' => 'shipping_country',
            'shipping_address.shipping_country_code' => 'shipping_country_code',
            'shipping_address.shipping_latitude' => 'shipping_latitude',
            'shipping_address.shipping_longitude' => 'shipping_longitude',

            // Save and flatten the billing address
            'billing_address' => 'billing_address',
            'billing_address.billing_first_name' => 'billing_first_name',
            'billing_address.billing_last_name' => 'billing_last_name',
            'billing_address.billing_name' => 'billing_name',
            'billing_address.billing_phone' => 'billing_phone',
            'billing_address.billing_company' => 'billing_company',
            'billing_address.billing_address1' => 'billing_address1',
            'billing_address.billing_address2' => 'billing_address2',
            'billing_address.billing_city' => 'billing_city',
            'billing_address.billing_province' => 'billing_province',
            'billing_address.billing_province_code' => 'billing_province_code',
            'billing_address.billing_zip' => 'billing_zip',
            'billing_address.billing_country' => 'billing_country',
            'billing_address.billing_country_code' => 'billing_country_code',
            'billing_address.billing_latitude' => 'billing_latitude',
            'billing_address.billing_longitude' => 'billing_longitude',

            'phone' => 'phone',
            'total_price' => 'total_price',
            'total_line_items_price' => 'total_line_items_price',
            'total_price_usd' => 'total_price_usd',
            'subtotal_price' => 'subtotal_price',
            'total_tax' => 'total_tax',
            'total_discounts' => 'total_discounts',
            'total_weight' => 'total_weight',
            'discount_codes' => 'discount_codes',
            'discount_applications' => 'discount_applications',
            'credit_card_number_last4' => 'credit_card_number_last4',
            'credit_card_company' => 'credit_card_company',
            'tax_lines' => 'tax_lines',
            'tax_included' => 'tax_included',
            'total_tip_received' => 'total_tip_received',
            'presentment_currency' => 'presentment_currency',
            'subtotal_price_set' => 'subtotal_price_set',
            'total_discounts_set' => 'total_discounts_set',
            'total_line_items_price_set' => 'total_line_items_price_set',
            'total_price_set' => 'total_price_set',
            'total_shipping_price_set' => 'total_shipping_price_set',
            'total_tax_set' => 'total_tax_set',
            'gateway' => 'gateway',
            'payment_details' => 'payment_details',
            'payment_gateway_names' => 'payment_gateway_names',
            'processing_method' => 'processing_method',
            'confirmed' => 'confirmed',
            'financial_status' => 'financial_status',
            'fulfillment_status' => 'fulfillment_status',
            'fulfillments' => 'fulfillments',
            'refunds' => 'refunds',
            'cancel_reason' => 'cancel_reason',
            'source_identifier' => 'source_identifier',
            'source_name' => 'source_name',
            'source_url' => 'source_url',
            'tags' => 'tags',
            'client_details' => 'client_details',
            'client_details.browser_ip' => 'client_details_browser_ip',
            'client_details.accept_language' => 'client_details_accept_language',
            'client_details.user_agent' => 'client_details_user_agent',
            'client_details.session_hash' => 'client_details_session_hash',
            'client_details.browser_width' => 'client_details_browser_width',
            'client_details.browser_height' => 'client_details_browser_height',
            'device_id' => 'device_id',
            'buyer_accepts_marketing' => 'buyer_accepts_marketing',
            'reference' => 'reference',
            'referring_site' => 'referring_site',
            'landing_site' => 'landing_site',
            'landing_site_ref' => 'landing_site_ref',
            'note' => 'note',
            'note_attributes' => 'note_attributes',
            'fulfilled_at' => 'fulfilled_at',
            'closed_at' => 'closed_at',
            'cancelled_at' => 'cancelled_at',
            'refunded_at' => 'refunded_at',
            'processed_at' => 'processed_at',
            'created_at' => 'store_created_at',
            'updated_at' => 'store_updated_at',
        ],
        'import_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyOrderImport',
        'update_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyOrderUpdate',
        'refund_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyOrderRefund',
        'cancel_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyOrderCancel',
        'items' => [
            'model' => \Dan\Shopify\Laravel\Models\OrderItem::class,
            'map' => [
                'id' => 'store_line_item_id',
                'product_id' => 'store_product_id',
                'variant_id' => 'store_variant_id',
                'fulfillment_id' => 'store_fulfillment_id',
                'name' => 'name',
                'title' => 'title',
                'variant_title' => 'variant_title',
                'quantity' => 'quantity',
                'price' => 'price',
                'price_set' => 'price_set',
                'total_discount' => 'total_discount',
                'total_discount_set' => 'total_discount_set',
                'gift_card' => 'gift_card',
                'grams' => 'grams',
                'sku' => 'sku',
                'vendor' => 'vendor',
                'properties' => 'properties',
                'taxable' => 'taxable',
                'tax_lines' => 'tax_lines',
                'requires_shipping' => 'requires_shipping',
                'product_exists' => 'product_exists',
                'fulfillment_service' => 'fulfillment_service',
                'fulfillment_status' => 'fulfillment_status',
                'fulfillable_quantity' => 'fulfillable_quantity',
                'fulfilled_at' => 'fulfilled_at',
                'variant_inventory_management' => 'variant_inventory_management',
            ],
            'import_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyLineItemImport',
            'update_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyLineItemUpdate',
            'refund_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyLineItemRefund',
            'types' => [
                // key    => label
                'product' => 'Product'
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Product settings
    |--------------------------------------------------------------------------
    |
    | Settings for your Product models
    |
    */
    'products' => [
        'model' => \Dan\Shopify\Laravel\Models\Product::class,
        'map' => [
            'id' => 'store_product_id',
            'admin_graphql_api_id' => 'admin_graphql_api_id',
            'title' => 'title',
            'body_html' => 'body_html',
            'vendor' => 'vendor',
            'product_type' => 'product_type',
            'handle' => 'handle',
            'template_suffix' => 'template_suffix',
            'published_scope' => 'published_scope',
//            'tags' => 'tags',
            'options' => 'options',
            'images' => 'images',
            'image' => 'image',
            'published_at' => 'published_at',
            'created_at' => 'store_created_at',
            'updated_at' => 'store_updated_at',
        ],
        'import_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyProductImport',
        'update_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyProductUpdate',
        'variants' => [
            'model' => \Dan\Shopify\Laravel\Models\Variant::class,
            'map' => [
                'id' => 'store_variant_id',
                'product_id' => 'store_product_id',
                'image_id' => 'store_image_id',
                'sku' => 'sku',
                'barcode' => 'barcode',
                'title' => 'title',
                'price' => 'price',
                'compare_at_price' => 'compare_at_price',
                'position' => 'position',
                'grams' => 'grams',
                'option1' => 'option1',
                'option2' => 'option2',
                'option3' => 'option3',
                'weight' => 'weight',
                'weight_unit' => 'weight_unit',
                'taxable' => 'taxable',
                'requires_shipping' => 'requires_shipping',
                'inventory_item_id' => 'inventory_item_id',
                'inventory_quantity' => 'inventory_quantity',
                'old_inventory_quantity' => 'old_inventory_quantity',
                'inventory_policy' => 'inventory_policy',
                'inventory_management' => 'inventory_management',
                'created_at' => 'store_created_at',
                'updated_at' => 'store_updated_at',
            ],
            'import_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyVariantImport',
            'update_qualifier' => 'Dan\Shopify\Laravel\Support\Util::qualifyVariantUpdate',
        ]
    ],

    'sync' => [
        // Is sync enabled?
        'enabled' => env('SHOPIFY_SYNC_ENABLED', true),

        // A timeout to prevent two jobs concurrently syncing the same store.
        'lock' => env('SHOPIFY_SYNC_LOCK', 720),

        // If you don't like the default logging pattern, switch it up.
        'log_channel' => env('SHOPIFY_SYNC_LOG_CHANNEL', 'stack'),

        // How many orders can we fetch at a time?
        'limit' => env('SHOPIFY_SYNC_LIMIT', 128),

        // How long to sync tasks have before they time out?
        'max_execution_time' => env('SHOPIFY_SYNC_MAX_EXECUTION_TIME', 300),

        // How long to wait between pages requests from Shopify?
        'sleep_between_page_requests' => env('ORDERS_SYNC_SLEEP_BETWEEN_PAGE_REQUESTS', 1),

        // Uninstall the app if one of these HTTP codes is returned from an API request.
        'uninstallable_codes' => array_filter(explode(',', env('SHOPIFY_SYNC_UNINSTALLABLE_CODES', '402,403'))),

        // How many minutes are we required to wait until an order can be updated.
        'update_lock_minutes' => env('SHOPIFY_SYNC_UPDATE_LOCK_MINUTES', 10),

        'throw_processing_exceptions' => env('SHOPIFY_SYNC_THROW_PROCESSING_EXCEPTIONS', false),
    ],
];
