<?php

namespace Dan\Shopify\Laravel\Traits;

use Dan\Shopify\Laravel\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use More\Laravel\Model;
use More\Laravel\Util;

/**
 * Class BelongsToStore
 *
 * For any table with `store_type` and `store_id` columns
 *
 * @method static Builder|static forStore(Model $store)
 * @property Store
 * @property int $store_id
 * @property string $store_type
 */
trait BelongsToStore
{
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function store()
    {
        return $this->morphTo();
    }

    /**
     * @param Builder $query
     * @param Model $store
     * @return Builder|static
     */
    public function scopeForStore($query, Model $store)
    {
        return $query->where("{$this->getTable()}.store_type", Util::rawClass($store))
            ->where("{$this->getTable()}.store_id", $store->getKey());
    }
}
