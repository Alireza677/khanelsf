<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class CartService
{
    private const SESSION_KEY = 'shop_cart';

    public function items(): Collection
    {
        return collect(session(self::SESSION_KEY, []))
            ->map(fn (array $item): array => [
                ...$item,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'total' => max(1, (int) ($item['quantity'] ?? 1)) * (float) ($item['unit_price'] ?? 0),
            ])
            ->values();
    }

    public function add(Product $product, int $quantity = 1): void
    {
        $items = $this->items()->keyBy('product_id');
        $quantity = max(1, $quantity);
        $existing = $items->get($product->id);

        $items->put($product->id, [
            'product_id' => $product->id,
            'title' => $product->title,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'unit_price' => $product->currentPrice(),
            'quantity' => ($existing['quantity'] ?? 0) + $quantity,
            'image' => $product->featuredImageUrl('thumb'),
        ]);

        session([self::SESSION_KEY => $items->values()->all()]);
    }

    public function update(array $quantities): void
    {
        $items = $this->items()
            ->map(function (array $item) use ($quantities): ?array {
                $quantity = (int) ($quantities[$item['product_id']] ?? $item['quantity']);

                if ($quantity < 1) {
                    return null;
                }

                return [
                    ...$item,
                    'quantity' => $quantity,
                ];
            })
            ->filter()
            ->values();

        session([self::SESSION_KEY => $items->all()]);
    }

    public function remove(int $productId): void
    {
        session([
            self::SESSION_KEY => $this->items()
                ->reject(fn (array $item): bool => (int) $item['product_id'] === $productId)
                ->values()
                ->all(),
        ]);
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public function subtotal(): float
    {
        return (float) $this->items()->sum('total');
    }

    public function count(): int
    {
        return (int) $this->items()->sum('quantity');
    }

    public function isEmpty(): bool
    {
        return $this->items()->isEmpty();
    }
}
