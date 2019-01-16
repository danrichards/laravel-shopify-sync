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
use Illuminate\Support\Collection as BaseCollection;

/**
 * Class ProductService
 */
class ProductService extends AbstractService
{
    /** @var Product|null $product */
    protected $product = null;

    /** @var array $product_data */
    protected $product_data = [];

    /** @var Collection|null $variants */
    protected $variants;

    /** @var array $variants_data */
    protected $variants_data = [];

    /** @var Collection|null */
    protected $filtered_variants;

    /** @var Collection|null $variants */
    protected $filtered_variants_data = null;

    /** @var bool $imported */
    protected $imported;

    /** @var bool $updated */
    protected $updated;

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

        $this->init($store, $product_data, $product);
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

            $this->fillMap($this->product_data);
            $this->product->fill($attributes);
            $this->product->save();

            foreach ($this->getFilteredVariants() as $variant) {
                $variant->save();
                $this->variants[$variant->store_variant_id] = $variant;
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
     * @param array $variant_data
     * @param array $attributes
     * @return Variant
     */
    protected function fillNewVariantMap(array $variant_data, array $attributes = [])
    {
        $vm = config('shopify.products.variants.model');
        $variant = $this->fillVariantMap(new $vm, $variant_data, $attributes);
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
     * @param Variant $variant
     * @param array $variant_data
     * @param array $attributes
     * @return Variant
     */
    protected function fillVariantMap(Variant $variant, array $variant_data, array $attributes = [])
    {
        $mapped_data = $this->util()->mapData(
            $data = $variant_data,
            $map = config('shopify.products.variants.map'),
            $model = $this->product->exists ? $this->product : config('shopify.products.variants.model'));

        $data = $attributes
            + $mapped_data
            + ['synced_at' => new Carbon('now')];

        $variant->fill($data);

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
    public function getFilteredVariants()
    {
        // The variants are already cached, send them back
        if (! is_null($this->filtered_variants)) {
            return $this->filtered_variants;
        }

        $this->filtered_variants = $this->getFilteredVariantsData()
            ->map(function(array $d) {
                if ($v = $this->getVariants()->get($d['id'])) {
                    return $this->fillVariantMap($v, $d);
                }

                return $this->fillNewVariantMap($d);
            });

        return $this->filtered_variants;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFilteredVariantsData()
    {
        // The variants are already cached, send them back
        if (! is_null($this->filtered_variants_data)) {
            return $this->filtered_variants_data;
        }

        $variants_data = $this->product_data['variants'] ?? [];

        return $this->filtered_variants_data = collect($variants_data)
            ->filter(function(array $d) {
                if ($v = $this->getVariants()->get($d['id'])) {
                    $p = $this->product;
                    return $this->util()::filterVariantUpdate($d, $p, $v);
                }

                return $this->util()::filterVariantImport($d);
            })
            ->keyBy('id');
    }

    /**
     * @return Collection|null
     */
    public function getVariants(): ?Collection
    {
        if (! is_null($this->variants)) {
            return $this->variants;
        }

        return $this->variants = $this->product
            ->variants()
            ->withTrashed()
            ->get()
            ->keyBy('store_variant_id');
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

            $this->fillMap($this->product_data);
            $this->product->fill($attributes);
            $this->product->save();

            foreach ($this->getFilteredVariants() as $variant) {
                $variant->save();
                $this->variants[$variant->store_variant_id] = $variant;
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

    /**
     * @param Store $store
     * @param array $product_data
     * @param Product $product
     * @return $this
     */
    protected function init(Store $store, array $product_data, Product $product = null)
    {
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
        $this->imported = $this->product->exists;
        $this->updated = false;

        return $this;
    }
}
