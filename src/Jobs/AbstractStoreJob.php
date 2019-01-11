<?php

namespace Dan\Shopify\Laravel\Jobs;

use Cache;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Shopify;
use Log;

/**
 * Class AbstractStoreJob
 */
abstract class AbstractStoreJob extends AbstractJob
{
    /** @var Store $store */
    protected $store;

    /**
     * Create a new job instance.
     *
     * @param Store $store
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * Execute the job.
     *
     * @abstract
     * @return void
     */
    public function handle() {}

    /**
     * @return Store
     */
    public function getStore(): Store
    {
        return $this->store;
    }

    /**
     * @return Shopify
     */
    public function getApiClient()
    {
        return new Shopify($this->getStore()->getShop(), $this->getStore()->getToken());
    }

    /**
     * @param Store $store
     * @return bool
     */
    public static function hasLockFor(Store $store)
    {
        $has_lock = boolval(Cache::get(__CLASS__.'|'.$store->getKey()));

        if ($has_lock) {
            static::storeMsg($store, 'has_lock', [], 'warning');
        }
    }

    /**
     * @param Store $store
     * @param int $minutes
     */
    public static function lock(Store $store, $minutes = null)
    {
        $minutes = $minutes ?: config('shopify.sync.lock');
        Cache::put(__CLASS__.'|'.$store->getKey(), 1, $minutes);

        static::storeMsg($store, 'locked', [], 'info');
    }

    /**
     * @param Store $store
     */
    public static function unlock(Store $store)
    {
        Cache::forget(__CLASS__.'|'.$store->getKey());

        static::storeMsg($store, 'unlocked', [], 'info');
    }

    /**
     * @param string $msg
     * @param array $data
     * @param string $level
     */
    protected function msg($msg = '', array $data = [], $level = 'error')
    {
        $data += ['job_id' => optional($this->job)->getJobId()];
        static::storeMsg($this->store, $msg, $data, $level);
    }

    /**
     * @param Store $store
     * @param string $msg
     * @param array $data
     * @param string $level
     */
    protected static function storeMsg(Store $store, $msg = '', array $data = [], $level = 'error')
    {
        $parts = explode('\\', get_called_class());
        $parts = array_slice($parts, 3);
        $parts = array_map('\Illuminate\Support\Str::snake', $parts);
        $parts[] = $store->myshopify_domain;
        $parts[] = $msg;

        $msg = implode(':', array_filter($parts));
        $data += $store->compact();

        Log::channel(config('shopify.sync.log_channel'))->$level((string) $msg, (array) $data);
    }
}
