<?php

namespace Dan\Shopify\Laravel\Support;

use Dan\Shopify\Laravel\Events\Stores\Created;
use Dan\Shopify\Laravel\Events\Stores\Updated;
use Dan\Shopify\Laravel\Models\Store;
use DB;
use Exception;

/**
 * Class StoreService
 */
class StoreService extends AbstractService
{
    /** @var array $store_data */
    protected $store_data = [];

    /**
     * ProductService constructor.
     *
     * @param array $store_data
     * @param Store|null $store
     */
    public function __construct(array $store_data, Store $store = null)
    {
        $store_model = config('shopify.stores.model');
        $this->store = $store ? $store : new $store_model;
        $this->store_data = $store_data;
        parent::__construct($this->store);
    }

    /**
     * @param array $attributes
     * @return Store|null
     * @throws Exception
     */
    public function create(array $attributes = [])
    {
        return $this->save($attributes, __FUNCTION__, Created::class);
    }

    /**
     * Include complete store_data
     *
     * @param array $store_data
     * @return Store
     */
    protected function fillMap(array $store_data)
    {
        $mapped_data = $this->util()->mapData(
            $data = $store_data,
            $map = config('shopify.stores.map'),
            $model = $this->store->exists ? $this->store : config('shopify.stores.model'));

        $mapped_data = $this->util()::truncateFields($mapped_data, config('shopify.stores.fields_max_length'));

        return $this->store->fill($mapped_data);
    }

    /**
     * @param $attributes
     * @param string $verb
     * @param string $event
     * @return Store|null
     * @throws Exception
     */
    protected function save($attributes, $verb = 'create', $event = Created::class)
    {
        try {
            DB::beginTransaction();

            $this->fillMap($this->store_data);
            $this->store->fill($attributes);
            $this->store->save();

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->store->exists = false;

            $trace = $this->util()::exceptionArr($e);

            $this->msg($verb, compact('trace'), 'emergency');

            if (config('shopify.sync.throw_processing_exceptions')) {
                throw $e;
            }
        }

        if ($this->store) {
            event(new $event($this->store));
        }

        return $this->store;
    }

    /**
     * @param string $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->store->fill(compact('token'));
        return $this;
    }

    /**
     * @param array $attributes
     * @return Store
     * @throws Exception
     */
    public function update(array $attributes =  [])
    {
        return $this->save($attributes, __FUNCTION__, Updated::class);
    }
}
