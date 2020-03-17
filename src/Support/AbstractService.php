<?php

namespace Dan\Shopify\Laravel\Support;

use Dan\Shopify\Laravel\Models\Store;
use Log;

/**
 * Class AbstractService
 */
class AbstractService
{
    /** @var Store $store */
    protected $store;

    /**
     * AbstractService constructor.
     *
     * @param Store $store
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * @return Store
     */
    public function getStore(): Store
    {
        return $this->store;
    }

    /**
     * @param string $msg
     * @param array $data
     * @param string $level
     * @return void
     */
    public function msg($msg = '', $data = [], $level = 'emergency')
    {
        $store = $this->getStore();

        $parts = explode('\\', get_called_class());
        $parts = array_slice($parts, 3);
        $parts = array_map('\Illuminate\Support\Str::snake', $parts);
        $parts[] = $store->myshopify_domain;
        $parts[] = $msg;

        $msg = implode(':', array_filter($parts));
        $data += $store->compact();

        Log::channel(config('shopify.sync.log_channel'))
            ->$level((string) $msg, (array) $data);
    }

    /**
     * @return Util
     */
    protected function util()
    {
        return app(config('shopify.util'));
    }
}