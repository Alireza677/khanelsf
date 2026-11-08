<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CartService
{
    public const SESSION_KEY = 'shop_cart';

    private const REVIEWS_KEY = 'shop_checkout_reviews';

    private const REVIEW_REQUIRED_KEY = 'shop_checkout_review_required';

    public const MAX_QUANTITY = 99;

    public function __construct(private readonly ProductPricingService $pricing) {}

    /** Safe presentation of stored rows, including rows needing repair/removal. */
    public function items(): Collection
    {
        $rawItems = $this->rawItems();
        if ($rawItems === []) {
            return collect();
        }
        $products = app(ProductPriceFreshness::class)->enabled()
            ? Product::query()->with('category')->whereIn('id', collect($rawItems)->filter(fn ($item) => is_array($item))->pluck('product_id'))->get()->keyBy('id')
            : collect();

        return collect($rawItems)->filter(fn ($item): bool => is_array($item))->map(function (array $item) use ($products): array {
            $error = null;
            $quantity = 0;
            $price = $total = '0.00';
            try {
                $quantity = $this->quantity($item['quantity'] ?? null);
                $price = $this->pricing->amount($item['unit_price'] ?? null);
                $total = $this->pricing->lineTotal($price, $quantity);
            } catch (ValidationException|InvalidArgumentException) {
                $error = 'اطلاعات این قلم معتبر نیست؛ تعداد را اصلاح یا قلم را حذف کنید.';
            }
            $product = $products->get($item['product_id'] ?? 0);
            if ($product && ! app(ProductPriceFreshness::class)->allowsPurchase($product)) {
                $error = 'قیمت این محصول '.ProductPriceFreshness::LABELS[app(ProductPriceFreshness::class)->status($product)].' است؛ استعلام بگیرید یا آن را حذف کنید.';
            }

            return [
                'product_id' => filter_var($item['product_id'] ?? null, FILTER_VALIDATE_INT) ?: 0,
                'title' => is_string($item['title'] ?? null) ? $item['title'] : 'محصول نامعتبر',
                'slug' => is_string($item['slug'] ?? null) ? $item['slug'] : '',
                'sku' => is_string($item['sku'] ?? null) ? $item['sku'] : null,
                'image' => is_string($item['image'] ?? null) ? $item['image'] : null,
                'quantity' => $quantity, 'unit_price' => $price, 'total' => $total, 'error' => $error,
                'inquiry_url' => $product && $error ? app(ProductPriceInquiry::class)->url($product) : null,
            ];
        })->values();
    }

    public function quantity(mixed $value, string $field = 'quantity'): int
    {
        if ((! is_int($value) && ! is_string($value)) || ! preg_match('/^[0-9]{1,2}$/D', (string) $value)
            || (int) $value < 1 || (int) $value > self::MAX_QUANTITY) {
            throw ValidationException::withMessages([$field => 'تعداد باید عدد صحیح بین ۱ تا ۹۹ باشد.']);
        }

        return (int) $value;
    }

    /** Must be called inside the order transaction when $lock is true. */
    public function authoritativeItems(bool $lock = false): Collection
    {
        $requested = [];
        foreach ($this->rawItems() as $item) {
            $id = is_array($item) ? ($item['product_id'] ?? null) : null;
            if ((! is_int($id) && ! is_string($id)) || ! filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                || isset($requested[(int) $id])) {
                throw ValidationException::withMessages(['cart' => 'ساختار سبد خرید معتبر نیست.']);
            }
            $requested[(int) $id] = $this->quantity($item['quantity'] ?? null, 'cart');
        }

        $query = Product::query()->with('category')->published()->whereIn('id', array_keys($requested))->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $products = $query->get()->keyBy('id');
        $items = collect();
        try {
            foreach ($requested as $id => $quantity) {
                $product = $products->get($id);
                if ($product) {
                    app(ProductPriceFreshness::class)->assertPurchasablePrice($product);
                }
                if (! $product || ! app(ProductAvailabilityService::class)->isPurchasable($product)) {
                    throw ValidationException::withMessages(['cart' => 'یک یا چند محصول دیگر منتشرشده یا قابل خرید نیستند؛ سبد خرید را بررسی و قلم نامعتبر را حذف کنید.']);
                }
                $price = $this->pricing->exactPrice($product);
                $items->push([
                    'product_id' => $id, 'title' => $product->title, 'slug' => $product->slug, 'sku' => $product->sku,
                    'quantity' => $quantity, 'unit_price' => $price, 'total' => $this->pricing->lineTotal($price, $quantity),
                ]);
            }
            $this->totalFor($items);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage().' امکان ثبت سفارش با این مبلغ وجود ندارد.']);
        }

        return $items;
    }

    public function totalFor(Collection $items): string
    {
        return $items->reduce(fn (string $sum, array $item): string => $this->pricing->addAmounts($sum, $item['total']), '0.00');
    }

    public function refresh(): Collection
    {
        $items = $this->authoritativeItems();
        // Old images are presentation only; financial snapshots never consume them.
        $images = $this->items()->pluck('image', 'product_id');
        $stored = $items->map(fn (array $item): array => [...$item, 'image' => $images->get($item['product_id'])]);
        if ($this->fingerprint($this->items()) !== $this->fingerprint($items)) {
            session([self::REVIEW_REQUIRED_KEY => true]);
        }
        session([self::SESSION_KEY => $stored->all()]);

        return $stored;
    }

    public function reviewToken(Collection $items): string
    {
        $token = Str::random(40);
        $reviews = (array) session(self::REVIEWS_KEY, []);
        $reviews[$token] = $this->fingerprint($items);
        session([self::REVIEWS_KEY => array_slice($reviews, -10, null, true)]);

        return $token;
    }

    public function assertReviewed(Collection $items, mixed $token): void
    {
        $reviews = (array) session(self::REVIEWS_KEY, []);
        $expected = is_string($token) ? ($reviews[$token] ?? null) : null;
        // Legacy direct POSTs are supported only before any review was issued or
        // required. Their server-stored add snapshot must still match current DB.
        if ($token === null && $reviews === [] && ! session(self::REVIEW_REQUIRED_KEY, false)) {
            $stored = $this->items();
            $expected = $stored->contains(fn (array $item): bool => $item['error'] !== null)
                ? null : $this->fingerprint($stored);
        }
        if (! is_string($expected) || ! hash_equals($expected, $this->fingerprint($items))) {
            session([self::REVIEW_REQUIRED_KEY => true]);
            throw ValidationException::withMessages(['cart' => 'قیمت یا اقلام سبد خرید تغییر کرده است. مبلغ به‌روز را بررسی کنید و برای تأیید، دوباره ثبت سفارش را بزنید.']);
        }
    }

    private function fingerprint(Collection $items): string
    {
        return hash('sha256', json_encode($items->sortBy('product_id')->map(fn (array $item): array => [
            $item['product_id'], $item['quantity'], $item['unit_price'],
        ])->values()->all(), JSON_THROW_ON_ERROR).ProductPricingService::CURRENCY);
    }

    public function add(Product $product, mixed $quantity = 1): void
    {
        $product = Product::query()->with('category')->published()->find($product->getKey());
        if (! $product) {
            throw ValidationException::withMessages(['cart' => 'این محصول منتشر نشده است.']);
        }
        app(ProductPriceFreshness::class)->assertPurchasablePrice($product);
        if (! app(ProductAvailabilityService::class)->isPurchasable($product)) {
            throw ValidationException::withMessages(['cart' => 'این محصول موجود و قابل خرید نیست.']);
        }
        $quantity = $this->quantity($quantity);
        $items = $this->rawItems();
        foreach ($items as $index => $item) {
            if (is_array($item) && (int) ($item['product_id'] ?? 0) === $product->id) {
                $quantity = $this->quantity($this->quantity($item['quantity'] ?? null) + $quantity);
                unset($items[$index]);
            }
        }
        try {
            $price = $this->pricing->exactPrice($product);
            $this->pricing->lineTotal($price, $quantity);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cart' => $exception->getMessage()]);
        }
        $items[] = [
            'product_id' => $product->id, 'title' => $product->title, 'slug' => $product->slug, 'sku' => $product->sku,
            'unit_price' => $price, 'quantity' => $quantity, 'image' => $product->featuredImageUrl('thumb'),
        ];
        session([self::SESSION_KEY => array_values($items)]);
    }

    public function update(array $quantities): void
    {
        $products = Product::query()->with('category')->whereIn('id', array_keys($quantities))->get()->keyBy('id');
        foreach ($quantities as $id => $quantity) {
            $product = $products->get($id);
            if ($product) {
                app(ProductPriceFreshness::class)->assertPurchasablePrice($product, 'quantities.'.$id);
            }
        }
        foreach ($quantities as $id => $quantity) {
            $this->quantity($quantity, 'quantities.'.$id);
        }
        $items = $this->rawItems();
        foreach ($items as &$item) {
            if (is_array($item) && array_key_exists($item['product_id'] ?? '', $quantities)) {
                $field = 'quantities.'.$item['product_id'];
                $item['quantity'] = $this->quantity($quantities[$item['product_id']], $field);
                try {
                    $this->pricing->lineTotal($this->pricing->amount($item['unit_price'] ?? null), $item['quantity']);
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([$field => $exception->getMessage()]);
                }
            }
        }
        unset($item);
        session([self::SESSION_KEY => $items]);
    }

    public function remove(int $productId): void
    {
        session([self::SESSION_KEY => collect($this->rawItems())
            ->reject(fn ($item): bool => is_array($item) && (int) ($item['product_id'] ?? 0) === $productId)->values()->all()]);
    }

    public function clear(): void
    {
        session()->forget([self::SESSION_KEY, self::REVIEWS_KEY, self::REVIEW_REQUIRED_KEY]);
    }

    public function subtotal(): string
    {
        try {
            return $this->totalFor($this->items());
        } catch (InvalidArgumentException) {
            // Keep an over-limit cart removable; it can never create an order.
            return '0.00';
        }
    }

    public function count(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->rawItems() === [];
    }

    private function rawItems(): array
    {
        $items = session(self::SESSION_KEY, []);

        return is_array($items) ? $items : [null];
    }
}
