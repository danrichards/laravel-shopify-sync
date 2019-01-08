<?php

namespace Dan\Shopify\Laravel\Providers;

use Dan\Shopify\Laravel\Console\ImportOrders;
use Dan\Shopify\Laravel\Console\ImportProductsAndVariants;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * Class ServiceProvider
 */
class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/shopify.php' => config_path('shopify.php'),
        ], 'config');

        $this->commands([
            ImportOrders::class,
            ImportProductsAndVariants::class,
        ]);

        $base = __DIR__.'/../../database/migrations/';
        $ts = date('Y_m_d_His');

        $this->publishes([
            "{$base}create_stores_table.php.stub" => database_path("/migrations/{$ts}_create_stores_table.php"),
            "{$base}create_products_table.php.stub" => database_path("/migrations/{$ts}_create_products_table.php"),
            "{$base}create_variants_table.php.stub" => database_path("/migrations/{$ts}_create_variants_table.php"),
            "{$base}create_orders_table.php.stub" => database_path("/migrations/{$ts}_create_orders_table.php"),
            "{$base}create_order_items_table.php.stub" => database_path("/migrations/{$ts}_create_order_items_table.php"),
            "{$base}create_customers_table.php.stub" => database_path("/migrations/{$ts}_create_customers_table.php"),
        ], 'migrations');
    }

    public function register()
    {
        $util = config('shopify.util');

        $this->app->singleton($util, function() use ($util) {
            return new $util;
        });
    }
}
