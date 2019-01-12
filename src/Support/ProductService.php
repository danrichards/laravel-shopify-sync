<?php

namespace Dan\Shopify\Laravel\Support;

use Dan\Shopify\Laravel\Events\Products\Created;
use Dan\Shopify\Laravel\Models\Product;
use Dan\Shopify\Laravel\Models\Store;
use Dan\Shopify\Laravel\Models\Variant;
use Carbon\Carbon;
use DB;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Class ProductService
 */
class ProductService extends AbstractService
{
    /** @var Product|null $product */
    protected $product = null;
    
    /** @var Collection|null $variants */
    protected $variants = null;

    /** @var array $product_data */
    protected $product_data = [];

    /** @var array $variants_data */
    protected $variants_data = [];

    /**
     * ProductService constructor.
     *
     * @param Store $store
     * @param array $product_data
     * @param Product|null $product
     */
    public function __construct(Store $store, array $product_data, Product $product = null)
    {
        parent::__construct($store);

        $this->product_data = $product_data;
        $this->variants_data = $this->product_data['variants'];

        // SANITY CHECK: Command is often queued, check again if the product exists.
        if ($product_data['id'] && empty($product)) {
            if ($existing = Product::findByStoreProductId($product_data['id'], $store)) {
                $this->product = $product = $existing;
            }
        }

        $product_model = config('shopify.products.model');

        $this->product = $product ?: new $product_model;

        if ($this->product->exists) {
            $this->variants = $this->product->variants()->withTrashed()->get();
        } else {
            $this->variants = new Collection();
        }
    }

    /**
     * @param array $attributes
     * @return Product|null
     * @throws Exception
     */
    public function create(array $attributes = [])
    {
        try {
            DB::beginTransaction();

            $new_product = $this->product->exists;

            $this->fillMap($this->product_data);
            $this->product->fill($attributes);
            $this->product->save();

            foreach ($this->variants_data as $variant_data) {
                if ($new_product) {
                    $variant = $this->fillNewVariant($variant_data);
                } else {
                    $variant = $this->variants
                        ->first(function($v) use ($variant_data) {
                            return $v->store_variant_id = $variant_data['id'];
                        });

                    $variant = $variant
                        ? $this->fillVariant($variant_data)
                        : $this->fillNewVariant($variant_data);
                }

                if (empty($variant->product_id)) {
                    $variant->product()->associate($this->product);
                }
                $variant->save();

                $this->variants->push($variant);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            $this->product = null;

            $trace = $this->util()::exceptionArr($e);

            $this->msg('create', compact('trace'), 'emergency');

            if (config('shopify.sync.throw_processing_exceptions')) {
                throw $e;
            }
        }

        if ($this->product) {
            event(new Created($this->product));
        }

        return $this->product;
    }

    /**
     * @param array $attributes
     * @return Variant
     */
    protected function fillNewVariant(array $attributes)
    {
        $variant = $this->fillVariant($attributes);
        $variant->product()->associate($this->product);

        return $variant;
    }

    /**
     * @param array $product_data
     * @param array $attributes
     * @return Product
     */
    protected function fillMap(array $product_data, array $attributes = [])
    {
        $mapped_data = $this->util()->mapData(
            $data = $product_data,
            $map = config('shopify.products.map'),
            $model = $this->product->exists ? $this->product : config('shopify.products.model'));

        $data = $attributes
            + $mapped_data
            + $this->store->unmorph('store')
            + ['synced_at' => new Carbon('now')];

        $this->product->fill($data);

        return $this->product;
    }

    /**
     * @param array $variant_data
     * @param array $attributes
     * @return Variant
     */
    protected function fillVariant(array $variant_data, array $attributes = [])
    {
        $variants_model = config('shopify.products.variants.model');

        /** @var Variant $variant */
        $variant = $this->variants->first(function(Variant $v) use ($variant_data) {
            return $v->store_variant_id = $variant_data['id'];
        }) ?: new $variants_model;

        $variant_dates = $variant->getDates();

        $shopify_data = [];

        $map = config('shopify.products.variants.map');

        foreach ($map as $key => $field) {
            $value = array_get($variant_data, $key);

            switch (true) {
                // Set those troublesome ISO 8601 dates.
                case in_array($field, $variant_dates):
                    $value = $value ? Carbon::parse($value) : $value;
                    break;
                case method_exists($this->util(), $method = Str::camel("fill_variant_{$field}")):
                    $value = $this->util()->$method($variant_data, $this->product);
                    break;
            }

            $shopify_data += [$field => $value];
        }

        $attributes += ['synced_at' => new Carbon('now')];

        $variant->fill($attributes + $shopify_data);

        if ($variant->exists) {
            $this->variants->push($variant);
        }

        return $variant;
    }

    /**
     * @return Product|null
     */
    public function getProduct(): ?Product
    {
        return $this->product;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getVariants()
    {
        return $this->variants;
    }

    /**
     * @param array $attributes
     * @return Product
     * @throws Exception
     */
    public function update(array $attributes =  [])
    {
        $this->fillMap($this->product_data);
        $this->product->fill($attributes);

        try {
            DB::beginTransaction();

            $this->product->save();

            foreach ($this->variants_data as $variant_data) {
                $variant = $this->variants
                    ->first(function($v) use ($variant_data) {
                        return $v->store_variant_id = $variant_data['id'];
                    });

                $variant = $variant
                    ? $this->fillVariant($variant_data)
                    : $this->fillNewVariant($variant_data);

                if (empty($variant->product_id)) {
                    $variant->product()->associate($this->product);
                }
                $variant->save();

                $this->variants->push($variant);

                $store_variant_ids = collect($this->variants_data)->pluck('id');

                $this->variants->reject(function(Variant $v) use ($store_variant_ids) {
                    if ($store_variant_ids->contains($v->store_variant_id)) {
                        return $v->delete();
                    }

                    return false;
                });
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            $trace = Util::exceptionArr($e);

            $this->msg('update', compact('trace'), 'emergency');

            if (config('services.shopify.app.throw_processing_exceptions')) {
                throw $e;
            }
        }

        return $this->product;
    }
}
