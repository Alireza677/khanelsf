<?php

namespace App\Services;

use App\Models\Product;

final class ProductAvailabilityService
{
    public const IN_STOCK = 'in_stock';

    public const OUT_OF_STOCK = 'out_of_stock';

    public function isPurchasable(Product $product): bool
    {
        return $product->isPurchasable();
    }

    public function stockStatus(Product $product): string
    {
        return $this->isPurchasable($product)
            ? self::IN_STOCK
            : self::OUT_OF_STOCK;
    }

    public function availability(Product $product): array
    {
        return [
            'purchasable' => $this->isPurchasable($product),
            'stockStatus' => $this->stockStatus($product),
            'hasStock' => (bool) $product->has_stock,
            'legacyStockStatus' => (string) $product->stock_status,
        ];
    }
}
