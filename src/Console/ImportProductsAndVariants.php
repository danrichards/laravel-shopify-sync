<?php

namespace Dan\Shopify\Laravel\Console;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Jobs\Products\ImportStore;
use Dan\Shopify\Laravel\Models\Store;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Log;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ImportProductsAndVariants
 */
class ImportProductsAndVariants extends AbstractCommand
{
    /** @var string $signature */
    protected $signature = 'shopify:import:products {--dryrun} {--connection=sync} {--created_at_min=} {--limit=} {--store_ids=any} {--last_product_import_at_max=now}';

    /** @var string $description */
    protected $description = 'Verify and sync recent products.';

    /** @var int $chunk_size */
    protected static $chunk_size = 100;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (config('shopify.sync.enabled', true) != true) {
            $this->error('Sync has been disabled. Please re-enable in your `shopify` configuration.');
            return;
        }

        $params = [
            'created_at_min' => $this->option('created_at_min'),
            'limit' => $this->option('limit')
        ];

        $this->logCount();

        $this->getQuery()
            ->chunk(static::$chunk_size, function($stores) use ($params) {
                /** @var Store $store */
                foreach ($stores as $store) {
                    $this->handleStore($store, $params);
                }
            });
    }

    /**
     * @return Builder
     */
    protected function getQuery()
    {
        $store_model = config('shopify.stores.model', Store::class);
        /** @var \Dan\Shopify\Laravel\Models\Store $store_model */
        $store_model = new $store_model;
        $table = $store_model->getTable();

        return (new $store_model)
            ->newQuery()
            ->select('stores.*')
            ->forInstalled()
            ->when($this->optionIds('store_ids'),
                $this->getStoreIdQuery($table),
                $this->getTimestampQuery($table));
    }

    /**
     * @param string $table
     * @return \Closure
     */
    protected function getStoreIdQuery($table)
    {
        return function(Builder $query, $store_ids) use($table) {
            $query->whereIn("{$table}.id", $store_ids);
        };
    }

    /**
     * @param string $table
     * @return \Closure
     */
    protected function getTimestampQuery($table)
    {
        return function(Builder $query) use ($table) {
            $query->whereNull("{$table}.last_product_import_at")
                ->orWhere(function (Builder $q2) use ($table) {
                    $last_product_import_at_max = new Carbon($this->option('last_product_import_at_max'));
                    $q2->where("{$table}.last_product_import_at", '<', $last_product_import_at_max)
                        ->when($this->option('created_at_min'), function(Builder $q3, $created_at_min) use ($table) {
                            $q3->where("{$table}.last_product_import_at", '<', $created_at_min);
                        });
                });
        };
    }

    /**
     * @param Store $store
     * @param array $params
     * @throws Exception
     */
    protected function handleStore($store, $params)
    {
        $dryrun = $this->option('dryrun');
        $connection = $this->option('connection');

        if (empty(config("queue.connections.{$connection}"))) {
            $this->throwConnectionError();
        }

        $this->log($store, $connection);

        $job = new ImportStore($store, $params, $connection, $dryrun);

        $connection == 'sync'
            ? dispatch_now($job)
            : dispatch($job)->onConnection($connection);
    }

    /**
     * @throws Exception
     */
    protected function throwConnectionError()
    {
        $valid_connections = array_keys(config('queue.connections'));
        $last_valid = array_pop($valid_connections);
        $message = "The queue connection \"{$this->option('connection')}\" is not valid. Use: "
            .implode(', ', $valid_connections). "or {$last_valid}";

        $this->error($message);

        throw new Exception($message);
    }

    /**
     * @param $store
     * @param $connection
     */
    protected function log($store, $connection): void
    {
        $verb = $connection == 'sync' ? 'dispatched' : 'queued';

        $msg = "console:shopify:products:import:{$store->myshopify_domain}:$verb";
        Log::channel(config('shopify.sync.log_channel'))
            ->info($msg, $store->compact());

        if ($this->verbosity % OutputInterface::VERBOSITY_VERBOSE == 0) {
            $this->info($msg);
        }
    }

    protected function logCount(): void
    {
        if ($this->v()) {
            $count = $this->getQuery()->count();
            $count
                ? $this->info("{$count} stores found. Syncing...")
                : $this->info('There are no stores that need syncing.');
        }
    }
}
