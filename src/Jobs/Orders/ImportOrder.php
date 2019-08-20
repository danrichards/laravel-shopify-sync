<?php

namespace Dan\Shopify\Laravel\Jobs\Orders;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
use Dan\Shopify\Laravel\Models\Order;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Laravel\Support\OrderService;
use Dan\Shopify\Laravel\Support\Util;
use Exception;
use Illuminate\Database\QueryException;

/**
 * Class ImportOrder
 */
class ImportOrder extends AbstractStoreJob
{
    /** @var array $order_data */
    protected $order_data;

    /**
     * ImportOrder constructor.
     *
     * @param Store $store
     * @param array $order_data
     */
    public function __construct(Store $store, array $order_data)
    {
        parent::__construct($store);

        $this->order_data = $order_data + [
            'user_id' => $this->store->user_id
        ];
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     */
    public function handle()
    {
        $store = $this->store;
        $data = $this->order_data;

        if ($order = Order::findByStoreOrderId($data['id'], $store)) {
            $wait = config('shopify.sync.update_lock_minutes');

            if ($order->created_at->lt(new Carbon("-{$wait} minutes"))) {
                // TODO: (new OrderService($store, $data, $order))->update();
                $this->msg('TODO: OrderService::update()', [], 'debug');
            } else {
                $this->msg('update_locked', $order->compact(), 'warning');
            }
        } else {
            try {
                (new OrderService($store, $data))->create();
            } catch (Exception $e) {
                $this->msg('failed', Util::exceptionArr($e), 'emergency');

                if (config('shopify.sync.throw_processing_exceptions')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @param Exception $e
     */
    public function failed(Exception $e)
    {
        switch (true) {
            case $e instanceof QueryException:
                $msg = "query_failure";
                $data = [
                    'msg' => $e->getMessage(),
                    'trace_first' => array_first($e->getTrace())
                ];
                if (str_contains($msg, 'Duplicate entry')) {
                    $this->job->delete();
                }

                return $this->msg($msg, $data, 'warning');
            default:
                $data = Util::exceptionArr($e);
                $msg = "queue_job_failed";

                return $this->msg($msg, $data, 'emergency');
        }
    }

    /**
     * @param string $msg
     * @param array $data
     * @param string $level
     */
    protected function msg($msg = '', array $data = [], $level = 'error')
    {
        $id = ! empty($this->order_data['id'])
            ? ":id:{$this->order_data['id']}"
            : ':id_missing';

        $data += ['id' => array_get($this->order_data, 'id')];

        parent::msg($msg.$id, $data, $level);
    }
}
