<?php

namespace App\Services;

use App\Shop\ShopFilters;
use InvalidArgumentException;

final class ShopFilterParser
{
    public function __construct(private readonly ProductPricingService $pricing) {}

    public function parse(array $query, ?string $routeCategory = null): ShopFilters
    {
        abort_if(count($query) > 30, 404);
        $data = [
            'q' => $this->text($query['q'] ?? '', 200),
            'category' => $this->text(array_key_exists('category', $query) ? ($query['category'] ?? '') : ($routeCategory ?? ''), 255),
            'brands' => $this->choices($query['brands'] ?? []),
            'tags' => $this->choices($query['tags'] ?? []),
            'attributes' => [],
            'min_price' => $this->price($query['min_price'] ?? null),
            'max_price' => $this->price($query['max_price'] ?? null),
            'stock' => $this->text($query['stock'] ?? '', 20),
            'featured' => $this->boolean($query['featured'] ?? false),
            'favorites' => $this->boolean($query['favorites'] ?? false),
            'on_sale' => $this->boolean($query['on_sale'] ?? false),
            'include_children' => $this->boolean($query['include_children'] ?? true),
            'sort' => $this->text($query['sort'] ?? 'default', 20),
        ];
        abort_unless(in_array($data['stock'], ['', 'in_stock', 'out_of_stock'], true) && isset(ShopFilters::SORTS[$data['sort']]), 404);
        $attributes = $query['attributes'] ?? [];
        abort_unless(is_array($attributes) && count($attributes) <= 20, 404);
        $total = count($data['brands']) + count($data['tags']);
        foreach ($attributes as $slug => $values) {
            $slug = $this->text($slug, 255);
            abort_if($slug === '', 404);
            $values = $this->choices($values);
            $total += count($values);
            if ($values !== []) {
                $data['attributes'][$slug] = $values;
            }
        }
        abort_if($total > 100, 404);
        ksort($data['attributes'], SORT_STRING);
        if ($data['min_price'] !== null && $data['max_price'] !== null && bccomp($data['min_price'], $data['max_price'], 2) > 0) {
            [$data['min_price'], $data['max_price']] = [$data['max_price'], $data['min_price']];
        }
        if ($data['category'] === '') {
            $data['include_children'] = true;
        }
        $page = $this->text($query['page'] ?? '1', 6);
        abort_unless(ctype_digit($page) && (int) $page >= 1 && (int) $page <= 100000, 404);

        return new ShopFilters($data, (int) $page);
    }

    private function choices(mixed $values): array
    {
        abort_unless(is_array($values) && count($values) <= 30 && array_is_list($values), 404);
        $values = array_map(fn ($value) => $this->text($value, 255), $values);
        $values = array_values(array_unique(array_filter($values, fn ($value) => $value !== '')));
        sort($values, SORT_STRING);

        return $values;
    }

    private function text(mixed $value, int $limit): string
    {
        abort_unless(is_string($value) || is_int($value), 404);
        $value = trim((string) $value);
        abort_if(mb_strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/u', $value), 404);

        return $value;
    }

    private function boolean(mixed $value): bool
    {
        abort_unless(in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false', ''], true), 404);

        return in_array($value, [true, 1, '1', 'true'], true);
    }

    private function price(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = $this->text($value, 32);
        try {
            return $this->pricing->amount($value);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}
