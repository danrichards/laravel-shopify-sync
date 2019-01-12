<?php

namespace Dan\Shopify\Laravel\Console;

use Dan\Shopify\Laravel\Support\StoreService;
use Dan\Shopify\Shopify;
use Exception;

/**
 * Class ImportStore
 */
class ImportStore extends AbstractCommand
{
    /** @var string $signature */
    protected $signature = 'shopify:import:store {myshopify_domain} {token}';

    /** @var string $description */
    protected $description = 'Update Shopify Store Information';

    /**
     * Execute the console command.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $token = $this->argument('token');
        $shop = $this->argument('myshopify_domain');
        $client = Shopify::make($shop, $token);

        try {
            $data = $client->shop() + compact('token');
            (new StoreService($data))->setToken($token)->create();
        } catch (Exception $e) {
            $this->error($e->getMessage());
            throw $e;
        }
    }
}