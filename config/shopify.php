<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customers settings
    |--------------------------------------------------------------------------
    |
    | Settings for your Customer models
    |
    */

    'customers' => [
        'import_filter' => 'Dan\Shopify\Laravel\Support\Util::filterCustomerImport',
        'map_from_orders' => [
            /* REQUIRED */ 'customer.id' => 'store_customer_id',
            /* REQUIRED */ 'customer.last_order_id' => 'store_last_order_id',
            'customer.admin_graphql_api_id' => 'admin_graphql_api_id',
            /* REQUIRED */ 'customer.email' => 'email',
            /* REQUIRED */ 'customer.first_name' => 'first_name',
            /* REQUIRED */ 'customer.last_name' => 'last_name',
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
            /* REQUIRED */ 'customer.store_created_at' => 'store_created_at',
            /* REQUIRED */ 'customer.store_updated_at' => 'store_updated_at',
        ],
        'map_updatable' => '*',
        'model' => \Dan\Shopify\Laravel\Models\Customer::class,
        'update_filter' => 'Dan\Shopify\Laravel\Support\Util::filterCustomerUpdate',
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
        'cancel_filter' => 'Dan\Shopify\Laravel\Support\Util::filterOrderCancel',
        'import_filter' => 'Dan\Shopify\Laravel\Support\Util::filterOrderImport',
        'log_tests' => env('SHOPIFY_ORDERS_LOG_TESTS', 1),
        'map' => [
            /* REQUIRED */ 'id' => 'store_order_id',
            /* REQUIRED */ 'customer.id' => 'store_customer_id',
            'user_id' => 'store_user_id',
            'app_id' => 'store_app_id',
            /* REQUIRED */ 'location_id' => 'store_location_id',
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
            'shipping_address.first_name' => 'shipping_first_name',
            'shipping_address.last_name' => 'shipping_last_name',
            'shipping_address.name' => 'shipping_name',
            'shipping_address.phone' => 'shipping_phone',
            'shipping_address.company' => 'shipping_company',
            'shipping_address.address1' => 'shipping_address1',
            'shipping_address.address2' => 'shipping_address2',
            'shipping_address.city' => 'shipping_city',
            'shipping_address.province' => 'shipping_province',
            'shipping_address.province_code' => 'shipping_province_code',
            'shipping_address.zip' => 'shipping_zip',
            'shipping_address.country' => 'shipping_country',
            'shipping_address.country_code' => 'shipping_country_code',
            'shipping_address.latitude' => 'shipping_latitude',
            'shipping_address.longitude' => 'shipping_longitude',

            // Save and flatten the billing address
            'billing_address' => 'billing_address',
            'billing_address.first_name' => 'billing_first_name',
            'billing_address.last_name' => 'billing_last_name',
            'billing_address.name' => 'billing_name',
            'billing_address.phone' => 'billing_phone',
            'billing_address.company' => 'billing_company',
            'billing_address.address1' => 'billing_address1',
            'billing_address.address2' => 'billing_address2',
            'billing_address.city' => 'billing_city',
            'billing_address.province' => 'billing_province',
            'billing_address.province_code' => 'billing_province_code',
            'billing_address.zip' => 'billing_zip',
            'billing_address.country' => 'billing_country',
            'billing_address.country_code' => 'billing_country_code',
            'billing_address.latitude' => 'billing_latitude',
            'billing_address.longitude' => 'billing_longitude',

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
            /* REQUIRED */ 'fulfillments' => 'fulfillments',
            /* REQUIRED */ 'refunds' => 'refunds',
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
            /* REQUIRED */ 'fulfilled_at' => 'fulfilled_at',
            /* REQUIRED */ 'closed_at' => 'closed_at',
            /* REQUIRED */ 'cancelled_at' => 'cancelled_at',
            /* REQUIRED */ 'refunded_at' => 'refunded_at',
            /* REQUIRED */ 'processed_at' => 'processed_at',
            /* REQUIRED */ 'created_at' => 'store_created_at',
            /* REQUIRED */ 'updated_at' => 'store_updated_at',
        ],
        'fields_max_length' => [
            'client_details_browser_ip' => 32,
            'shipping_phone' => 32,
            'shipping_address1' => 128,
            'shipping_address2' => 128,
            'gateway' => 64,
        ],
        'map_updatable' => '*',
        'model' => \Dan\Shopify\Laravel\Models\Order::class,
        'refund_filter' => 'Dan\Shopify\Laravel\Support\Util::filterOrderRefund',
        'update_filter' => 'Dan\Shopify\Laravel\Support\Util::filterOrderUpdate',
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
        'import_filter' => 'Dan\Shopify\Laravel\Support\Util::filterProductImport',
        'items' => [
            'import_filter' => 'Dan\Shopify\Laravel\Support\Util::filterLineItemImport',
            'map' => [
                /* REQUIRED */ 'id' => 'store_line_item_id',
                /* REQUIRED */ 'product_id' => 'store_product_id',
                /* REQUIRED */ 'variant_id' => 'store_variant_id',
                /* REQUIRED */ 'fulfillment_id' => 'store_fulfillment_id',
                'name' => 'name',
                /* REQUIRED */ 'title' => 'title',
                'variant_title' => 'variant_title',
                /* REQUIRED */ 'quantity' => 'quantity',
                /* REQUIRED */ 'price' => 'price',
                'price_set' => 'price_set',
                /* REQUIRED */ 'total_discount' => 'total_discount',
                'total_discount_set' => 'total_discount_set',
                'gift_card' => 'gift_card',
                'grams' => 'grams',
                /* REQUIRED */ 'sku' => 'sku',
                /* REQUIRED */ 'vendor' => 'vendor',
                /* REQUIRED */ 'properties' => 'properties',
                'taxable' => 'taxable',
                'tax_lines' => 'tax_lines',
                'requires_shipping' => 'requires_shipping',
                'product_exists' => 'product_exists',
                /* REQUIRED */ 'fulfillment_service' => 'fulfillment_service',
                /* REQUIRED */ 'fulfillment_status' => 'fulfillment_status',
                /* REQUIRED */ 'fulfillable_quantity' => 'fulfillable_quantity',
                /* REQUIRED */ 'fulfilled_at' => 'fulfilled_at',
                'variant_inventory_management' => 'variant_inventory_management',
            ],
            'model' => \Dan\Shopify\Laravel\Models\OrderItem::class,
            'partially_refunded_titles_append' => ' *Partially Refunded',
            'refund_filter' => 'Dan\Shopify\Laravel\Support\Util::filterLineItemRefund',
            'refunded_titles_append' => ' *Refunded',
            'update_filter' => 'Dan\Shopify\Laravel\Support\Util::filterLineItemUpdate',
        ],
        'map' => [
            /* REQUIRED */ 'id' => 'store_product_id',
            'admin_graphql_api_id' => 'admin_graphql_api_id',
            /* REQUIRED */ 'title' => 'title',
            /* REQUIRED */ 'body_html' => 'body_html',
            /* REQUIRED */ 'vendor' => 'vendor',
            /* REQUIRED */ 'product_type' => 'product_type',
            /* REQUIRED */ 'handle' => 'handle',
            /* REQUIRED */ 'template_suffix' => 'template_suffix',
            /* REQUIRED */ 'published_scope' => 'published_scope',
            'tags' => 'tags',
            /* REQUIRED */ 'options' => 'options',
            /* REQUIRED */ 'images' => 'images',
            /* REQUIRED */ 'image' => 'image',
            /* REQUIRED */ 'published_at' => 'published_at',
            /* REQUIRED */ 'created_at' => 'store_created_at',
            /* REQUIRED */ 'updated_at' => 'store_updated_at',
        ],
        'map_updatable' => '*',
        'model' => \Dan\Shopify\Laravel\Models\Product::class,
        'variants' => [
            'import_filter' => 'Dan\Shopify\Laravel\Support\Util::filterVariantImport',
            'map' => [
                /* REQUIRED */ 'id' => 'store_variant_id',
                /* REQUIRED */ 'product_id' => 'store_product_id',
                /* REQUIRED */ 'image_id' => 'store_image_id',
                /* REQUIRED */ 'sku' => 'sku',
                /* REQUIRED */ 'barcode' => 'barcode',
                /* REQUIRED */ 'title' => 'title',
                /* REQUIRED */ 'price' => 'price',
                /* REQUIRED */ 'compare_at_price' => 'compare_at_price',
                /* REQUIRED */ 'position' => 'position',
                /* REQUIRED */ 'grams' => 'grams',
                /* REQUIRED */ 'option1' => 'option1',
                /* REQUIRED */ 'option2' => 'option2',
                /* REQUIRED */ 'option3' => 'option3',
                /* REQUIRED */ 'weight' => 'weight',
                /* REQUIRED */ 'weight_unit' => 'weight_unit',
                /* REQUIRED */ 'taxable' => 'taxable',
                /* REQUIRED */ 'requires_shipping' => 'requires_shipping',
                /* REQUIRED */ 'inventory_item_id' => 'inventory_item_id',
                /* REQUIRED */ 'inventory_quantity' => 'inventory_quantity',
                'old_inventory_quantity' => 'old_inventory_quantity',
                /* REQUIRED */ 'inventory_policy' => 'inventory_policy',
                /* REQUIRED */ 'inventory_management' => 'inventory_management',
                /* REQUIRED */ 'created_at' => 'store_created_at',
                /* REQUIRED */ 'updated_at' => 'store_updated_at',
            ],
            'map_updatable' => '*',
            'model' => \Dan\Shopify\Laravel\Models\Variant::class,
            'update_filter' => 'Dan\Shopify\Laravel\Support\Util::filterVariantUpdate',
        ]
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
        'map' => [
            /* REQUIRED */ 'primary_location_id' => 'store_primary_location_id',
            /* REQUIRED */ 'name' => 'name',
            /* REQUIRED */ 'shop_owner' => 'shop_owner',
            /* REQUIRED */ 'email' => 'email',
            'customer_email' => 'customer_email',
            'domain' => 'domain',
            /* REQUIRED */ 'myshopify_domain' => 'myshopify_domain',
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
            /* REQUIRED */ 'primary_locale' => 'primary_locale',
            /* REQUIRED */ 'timezone' => 'timezone',
            'iana_timezone' => 'iana_timezone',
            /* REQUIRED */ 'currency' => 'currency',
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
            /* REQUIRED */ 'multi_location_enabled' => 'multi_location_enabled',
            'force_ssl' => 'force_ssl',
            'pre_launch_enabled' => 'pre_launch_enabled',
            'requires_extra_payments_agreement' => 'requires_extra_payments_agreement',
            'password_enabled' => 'password_enabled',
            'enabled_presentment_currencies' => 'enabled_presentment_currencies',
            'taxes_included' => 'taxes_included',
            'tax_shipping' => 'tax_shipping',
            'county_taxes' => 'county_taxes',
            'setup_required' => 'setup_required',
            /* REQUIRED */ 'created_at' => 'store_created_at',
            /* REQUIRED */ 'updated_at' => 'store_updated_at',
        ],
        'fields_max_length' => [
            'plan_name' => 32,
            'plan_display_name' => 16,
            'primary_locale' => 8
        ],
        'map_updatable' => '*',
        'model' => \Dan\Shopify\Laravel\Models\Store::class,
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
    'util' => \Dan\Shopify\Laravel\Support\Util::class,
];
