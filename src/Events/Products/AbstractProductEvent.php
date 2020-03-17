<?php

namespace Dan\Shopify\Laravel\Events\Products;

use Dan\Shopify\Laravel\Models\Product;
use Illuminate\Queue\SerializesModels;

/**
 * Class AbstractProductEvent
 */
abstract class AbstractProductEvent
{
    use SerializesModels;

    /** @var Product $product */
    protected $product;

    /**
     * AbstractProductEvent constructor.
     *
     * @param Product $product
     */
    public function __construct(Product $product)
    {
        $this->product = $product->fresh();
    }

    /**
     * @return Product
     */
    public function getProduct()
    {
        return $this->product;
    }
}
