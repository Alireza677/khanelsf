<?php

namespace App\Services;

use App\Models\Product;
use InvalidArgumentException;

final class ProductPricingService
{
    public const CURRENCY = 'IRT';

    public const MAX_AMOUNT = '9999999999.99';

    public function regularPrice(Product $product): float
    {
        return (float) $this->amount($product->getAttributes()['price'] ?? null);
    }

    public function salePrice(Product $product): ?float
    {
        $sale = $this->exactSalePrice($product);

        return $sale === null ? null : (float) $sale;
    }

    public function effectivePrice(Product $product): float
    {
        // Preserve the existing presentation API; transactions use exactPrice().
        return (float) $this->exactPrice($product);
    }

    public function exactPrice(Product $product): string
    {
        return $this->exactSalePrice($product) ?? $this->amount($product->getAttributes()['price'] ?? null);
    }

    private function exactSalePrice(Product $product): ?string
    {
        $attributes = $product->getAttributes();
        $regular = $this->amount($attributes['price'] ?? null);
        $rawSale = $attributes['sale_price'] ?? null;

        if ($rawSale === null || $rawSale === '') {
            return null;
        }

        $sale = $this->amount($rawSale);

        return bccomp($sale, $regular, 2) < 0 ? $sale : null;
    }

    public function amount(mixed $value): string
    {
        // SQLite PDO and legacy carts may supply a numeric scalar. Convert to text
        // before validation; never perform monetary arithmetic with that scalar.
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException('مبلغ محصول معتبر نیست.');
        }

        if (is_float($value) && ! is_finite($value)) {
            throw new InvalidArgumentException('مبلغ محصول معتبر نیست.');
        }
        $value = is_float($value)
            ? json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)
            : (string) $value;
        if (strlen($value) > 32 || ! preg_match('/^\d+(?:\.\d{1,2})?$/D', $value)) {
            throw new InvalidArgumentException('مبلغ باید غیرمنفی و دارای حداکثر دو رقم اعشار باشد.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 10 || bccomp($value, self::MAX_AMOUNT, 2) > 0) {
            throw new InvalidArgumentException('مبلغ از ظرفیت مجاز سفارش بیشتر است.');
        }

        return $whole.'.'.str_pad($fraction, 2, '0');
    }

    public function lineTotal(string $unitPrice, int $quantity): string
    {
        return $this->amount(bcmul($this->amount($unitPrice), (string) $quantity, 2));
    }

    public function addAmounts(string $first, string $second): string
    {
        return $this->amount(bcadd($this->amount($first), $this->amount($second), 2));
    }

    public function formatAmount(string $amount): string
    {
        [$whole, $fraction] = explode('.', $this->amount($amount));
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole);
        $fraction = rtrim($fraction, '0');

        return $whole.($fraction === '' ? '' : '.'.$fraction);
    }

    public function currency(): string
    {
        return self::CURRENCY;
    }
}
