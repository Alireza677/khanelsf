<?php

namespace App\Shop;

final class ShopFilters
{
    public const SORTS = [
        'default' => 'ترتیب پیش‌فرض فروشگاه', 'newest' => 'جدیدترین', 'oldest' => 'قدیمی‌ترین',
        'price_asc' => 'قیمت از کم به زیاد', 'price_desc' => 'قیمت از زیاد به کم', 'title' => 'عنوان محصول',
    ];

    public function __construct(public readonly array $data, public readonly int $page = 1) {}

    public function with(array $changes): self
    {
        return new self(array_replace($this->data, $changes));
    }

    public function query(bool $withPage = false): array
    {
        $query = $this->data;
        unset($query['category']);
        foreach ($query as $key => $value) {
            if ($value === '' || $value === null || $value === [] || ($value === false && $key !== 'include_children')
                || ($key === 'sort' && $value === 'default') || ($key === 'include_children' && $value === true)) {
                unset($query[$key]);
            } elseif (is_bool($value)) {
                $query[$key] = $value ? '1' : '0';
            }
        }
        if ($withPage && $this->page > 1) {
            $query['page'] = $this->page;
        }
        ksort($query);

        return $query;
    }

    public function url(bool $withPage = false): string
    {
        $url = $this->baseUrl();
        $query = $this->query($withPage);

        return $url.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '');
    }

    public function baseUrl(): string
    {
        return $this->data['category'] !== '' ? route('shop.category', $this->data['category']) : route('shop.index');
    }

    public function isFiltered(): bool
    {
        return $this->query() !== [];
    }

    /** Form GET submissions retain only normalized state and deliberately omit page. */
    public function hiddenInputs(array $except = []): array
    {
        $inputs = [];
        $flatten = function (array $values, string $prefix = '') use (&$flatten, &$inputs): void {
            foreach ($values as $key => $value) {
                $name = $prefix === '' ? (string) $key : $prefix.'['.$key.']';
                if (is_array($value)) {
                    $flatten($value, $name);
                } else {
                    $inputs[] = ['name' => $name, 'value' => $value];
                }
            }
        };
        $flatten(array_diff_key($this->query(), array_flip($except)));

        return $inputs;
    }
}
