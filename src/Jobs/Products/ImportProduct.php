<?php

namespace Dan\Shopify\Laravel\Jobs\Products;

use Carbon\Carbon;
use Dan\Shopify\Laravel\Jobs\AbstractStoreJob;
use Dan\Shopify\Laravel\Models\Product;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Laravel\Support\ProductService;
use Dan\Shopify\Laravel\Support\Util;
use Exception;
use Illuminate\Database\QueryException;

/**
 * Class ImportProduct
 */
class ImportProduct extends AbstractStoreJob
{
    /** @var array $product_data */
    protected $product_data;

    /**
     * ImportProduct constructor.
     *
     * @param Store $store
     * @param array $product_data
     */
    public function __construct(Store $store, array $product_data)
    {
        parent::__construct($store);

        $this->product_data = $product_data;
    }

    /**
     * Execute the job.
     *
     * @throws Exception
     */
    public function handle()
    {
        $store = $this->store;
        $data = $this->product_data;

        if ($product = Product::findByStoreProductId($data['id'], $store)) {
            $wait = config('shopify.sync.update_lock_minutes');

            if ($product->created_at->lt(new Carbon("-{$wait} minutes"))) {
                (new ProductService($store, $data, $product))->update();
            } else {
                $this->msg('update_locked', [], 'warning');
            }
        } else {
            try {
                (new ProductService($store, $data))->create();
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
        $id = ! empty($this->product_data['id'])
            ? ":id:{$this->product_data['id']}"
            : ':id_missing';

        $data += ['id' => array_get($this->product_data, 'id')];

        parent::msg($msg.$id, $data, $level);
    }
}
