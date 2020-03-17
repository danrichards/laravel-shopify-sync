<?php

namespace Dan\Shopify\Laravel\Jobs\Orders;

use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
use Dan\Shopify\Laravel\Models\Order;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Laravel\Support\OrderService;
use Dan\Shopify\Laravel\Support\Util;
use Exception;
use Log;

/**
 * Class ImportStoreOrderRisk
 */
class ImportStoreOrderRisk extends AbstractStoreJob
{
    /** @var array $order_ids */
    protected $order_ids;

    /** @var bool $dryrun */
    protected $dryrun;

    /** @var bool $now */
    protected $now;

    /**
     * ImportStoreOrderRisk constructor.
     *
     * @param Store $store
     * @param array $order_ids
     * @param string $connection
     * @param bool $dryrun
     */
    public function __construct(Store $store, array $order_ids = [], $connection = 'sync', $dryrun = false)
    {
        parent::__construct($store);

        $this->order_ids = $order_ids;
        $this->dryrun = $dryrun;
        $this->connection = $connection;
    }

    /**
     * @return void
     */
    public function handle()
    {
        if (empty($order_id = array_shift($this->order_ids))) {
            Log::channel(config('shopify.sync.log_channel'))
                ->info("cmd:shopify:orders:sync_risk:completed");

            return;
        }

        try {
            $store = $this->getStore();
            $order = Order::findOrFail($order_id);

            $this->dryrun
                ? $this->msg('dryrun', compact('order_id'), 'notice')
                : (new OrderService($store, [], $order))->updateRisk();

            sleep(config('services.shopify.sleep_between_requests'));

            $next = new static($store, $this->order_ids, $this->connection, $this->dryrun);

            $this->connection == 'sync'
                ? dispatch_now($next)
                : dispatch($next);
        } catch (Exception $e) {
            $this->msg('failed', Util::exceptionArr($e));
        }
    }
}
