<?php

namespace App\Services;

use App\Models\Product;

final class ProductPricingService
{
    public const CURRENCY = 'IRT';

    public function regularPrice(Product $product): float
    {
        return (float) $product->price;
    }

    public function salePrice(Product $product): ?float
    {
        return $product->hasSalePrice()
            ? (float) $product->sale_price
            : null;
    }

    public function effectivePrice(Product $product): float
    {
        return $this->salePrice($product) ?? $this->regularPrice($product);
    }

    public function currency(): string
    {
        return self::CURRENCY;
    }
}
