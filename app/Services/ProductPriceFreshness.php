<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductPriceHistory;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class ProductPriceFreshness
{
    public const LABELS = [
        'missing' => 'بدون قیمت', 'unverified' => 'تأییدنشده', 'valid' => 'معتبر',
        'expiring_soon' => 'نزدیک انقضا', 'expired' => 'منقضی',
    ];

    public function __construct(private readonly SettingsService $settings, private readonly ProductPricingService $pricing) {}

    public function enabled(): bool
    {
        return filter_var($this->settings->get('shop_price_freshness_enabled', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function days(Product $product): ?int
    {
        if ($product->price_validity_mode === 'unlimited') {
            return null;
        }
        if ($product->price_validity_mode === 'custom') {
            return max(1, (int) $product->price_validity_days);
        }

        return max(1, (int) ($product->category?->price_validity_days ?? $this->settings->get('shop_price_validity_days', 30)));
    }

    public function expiresAt(Product $product): ?CarbonInterface
    {
        $days = $this->days($product);

        return $product->price_verified_at && $days !== null
            ? $product->price_verified_at->copy()->addDays($days) : null;
    }

    public function status(Product $product): string
    {
        if (bccomp($this->pricing->exactPrice($product), '0', 2) <= 0) {
            return 'missing';
        }
        if (! $product->price_verified_at) {
            return 'unverified';
        }
        $expires = $this->expiresAt($product);
        if ($expires === null) {
            return 'valid';
        }
        if ($expires->lte(now())) {
            return 'expired';
        }

        return $expires->lte(now()->addDays($this->warningDays())) ? 'expiring_soon' : 'valid';
    }

    public function allowsPurchase(Product $product): bool
    {
        return ! $this->enabled() || in_array($this->status($product), ['valid', 'expiring_soon'], true);
    }

    public function assertPurchasablePrice(Product $product, string $field = 'cart'): void
    {
        if (! $this->allowsPurchase($product)) {
            throw ValidationException::withMessages([$field => "قیمت «{$product->title}» ".self::LABELS[$this->status($product)].' است؛ برای قیمت به‌روز استعلام بگیرید یا این قلم را از سبد حذف کنید.']);
        }
    }

    public function verify(Product $product, User $actor, ?array $expectedAmounts = null): Product
    {
        return $product->getConnection()->transaction(function () use ($product, $actor, $expectedAmounts): Product {
            $current = Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $expectedPrice = $expectedAmounts['expected_price'] ?? $product->price;
            $expectedSale = $expectedAmounts === null ? $product->sale_price : ($expectedAmounts['expected_sale_price'] ?? null);
            // A confirmation made against a stale form must never approve a newer amount.
            if ($current->price !== $expectedPrice || $current->sale_price !== $expectedSale) {
                throw ValidationException::withMessages(['price' => 'قیمت تغییر کرده است؛ صفحه را تازه کنید و دوباره بررسی کنید.']);
            }
            if (bccomp($this->pricing->exactPrice($current), '0', 2) <= 0) {
                throw ValidationException::withMessages(['price' => 'ابتدا قیمت مثبت و معتبر ثبت کنید.']);
            }
            $current->price_verified_at = now()->startOfSecond();
            if (! $current->save()) {
                throw ValidationException::withMessages(['price' => 'تأیید قیمت ذخیره نشد؛ دوباره تلاش کنید.']);
            }
            ProductPriceHistory::query()->create([
                'product_id' => $current->id, 'user_id' => $actor->id,
                'old_price' => $current->price, 'new_price' => $current->price,
                'old_sale_price' => $current->sale_price, 'new_sale_price' => $current->sale_price,
                'event' => 'price_verified', 'source' => 'manual',
                'price_verified_at' => $current->price_verified_at, 'occurred_at' => now(),
            ]);

            return $current;
        });
    }

    public function warningDays(): int
    {
        return max(0, (int) $this->settings->get('shop_price_warning_days', 3));
    }

    /** Prepare database-backed display data before the query-free template render. */
    public function presentation(Product $product, bool $withInquiry = true): array
    {
        $enabled = $this->enabled();
        $status = $enabled ? $this->status($product) : null;
        $blocked = $enabled && ! in_array($status, ['valid', 'expiring_soon'], true);

        return [
            'enabled' => $enabled,
            'status' => $status,
            'blocked' => $blocked,
            'show_verified_at' => $enabled && filter_var($this->settings->get('shop_price_show_verified_at', false), FILTER_VALIDATE_BOOLEAN),
            'inquiry_url' => $blocked && $withInquiry ? app(ProductPriceInquiry::class)->url($product) : null,
        ];
    }

    /** SQL expression keeps pagination, status filtering and expiry sorting in the database. */
    public function expiryExpression(): string
    {
        $default = max(1, (int) $this->settings->get('shop_price_validity_days', 30));
        $days = "CASE WHEN products.price_validity_mode = 'custom' THEN COALESCE(products.price_validity_days, 1) ELSE COALESCE((SELECT price_validity_days FROM product_categories WHERE product_categories.id = products.product_category_id), {$default}) END";
        $date = Product::query()->getConnection()->getDriverName() === 'sqlite'
            ? "datetime(products.price_verified_at, '+' || ({$days}) || ' days')"
            : "DATE_ADD(products.price_verified_at, INTERVAL ({$days}) DAY)";

        return "CASE WHEN products.price_validity_mode = 'unlimited' THEN NULL ELSE {$date} END";
    }

    public function filter(Builder $query, string $status): Builder
    {
        // Mirrors ProductPricingService: only a sale lower than regular is effective.
        $price = 'CASE WHEN products.sale_price IS NOT NULL AND products.sale_price < products.price THEN products.sale_price ELSE products.price END';
        if ($status === 'missing') {
            return $query->whereRaw("({$price}) <= 0");
        }
        $query->whereRaw("({$price}) > 0");
        if ($status === 'unverified') {
            return $query->whereNull('products.price_verified_at');
        }
        $query->whereNotNull('products.price_verified_at');
        $expiry = $this->expiryExpression();
        if ($status === 'expired') {
            return $query->whereRaw("({$expiry}) <= ?", [now()->toDateTimeString()]);
        }
        if ($status === 'expiring_soon') {
            return $query->whereRaw("({$expiry}) > ? AND ({$expiry}) <= ?", [now()->toDateTimeString(), now()->addDays($this->warningDays())->toDateTimeString()]);
        }

        return $query->where(fn (Builder $q) => $q->whereRaw("({$expiry}) IS NULL")->orWhereRaw("({$expiry}) > ?", [now()->addDays($this->warningDays())->toDateTimeString()]));
    }
}
