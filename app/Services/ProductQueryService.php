<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Shop\ShopFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ProductQueryService
{
    public function archive(ShopFilters $filters, ShopCategoryTree $tree, array $favoriteIds = [], bool $facets = false): Builder
    {
        $data = $filters->data;
        $pricing = app(ProductPricingService::class);
        $query = $this->publishedProducts()
            ->where(fn (Builder $query) => $query->whereNull('product_category_id')->orWhereIn('product_category_id', $tree->ids()))
            ->whereRaw($pricing->validPriceConditionSql());
        if ($data['category'] !== '') {
            $category = $tree->category($data['category']);
            abort_unless($category, 404);
            $query->whereIn('product_category_id', $tree->subtree($category, $data['include_children']));
        }
        if ($data['q'] !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $data['q']).'%';
            $query->where(function (Builder $query) use ($term): void {
                foreach (['title', 'excerpt', 'content', 'sku'] as $column) {
                    $query->orWhereRaw("products.{$column} LIKE ? ESCAPE '!'", [$term]);
                }
            });
        }
        foreach (['min_price' => '>=', 'max_price' => '<='] as $key => $operator) {
            if ($data[$key] !== null) {
                $query->whereRaw($pricing->effectivePriceSql()." {$operator} CAST(? AS DECIMAL(12,2))", [$data[$key]]);
            }
        }
        $query->when($data['favorites'], fn ($query) => $query->whereKey($favoriteIds))
            ->when($data['featured'], fn ($query) => $query->featured())
            ->when($data['on_sale'], fn ($query) => $query->whereRaw($pricing->salePriceConditionSql()))
            ->when($data['stock'] === 'in_stock', fn ($query) => $query->where('has_stock', true)->where('stock_status', 'in_stock'))
            ->when($data['stock'] === 'out_of_stock', fn ($query) => $query->where(fn ($query) => $query->where('has_stock', false)->orWhere('stock_status', '!=', 'in_stock')));
        if (! $facets) {
            $query->when($data['brands'] !== [], fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('status', 'active')->whereIn('slug', $data['brands'])))
                ->when($data['tags'] !== [], fn ($query) => $query->whereHas('tags', fn ($query) => $query->whereIn('slug', $data['tags'])));
            foreach ($data['attributes'] as $slug => $values) {
                $query->whereHas('attributeAssignments', fn ($query) => $query
                    ->whereHas('attribute', fn ($query) => $query->where('slug', $slug)->where('status', 'active')->where('is_filterable', true)->where('data_type', 'choice'))
                    ->whereHas('value', fn ($query) => $query->where('status', 'active')->whereIn('slug', $values)));
            }
        }

        return $query;
    }

    public function sortArchive(Builder $query, string $sort): Builder
    {
        $query->reorder();
        match ($sort) {
            'newest' => $query->orderByRaw('COALESCE(published_at, created_at) DESC'),
            'oldest' => $query->orderByRaw('COALESCE(published_at, created_at) ASC'),
            'price_asc' => $query->orderByRaw(app(ProductPricingService::class)->effectivePriceSql().' ASC'),
            'price_desc' => $query->orderByRaw(app(ProductPricingService::class)->effectivePriceSql().' DESC'),
            'title' => $query->orderBy('title'),
            default => $query->orderBy('sort_order')->orderByDesc('published_at'),
        };

        return $query->orderBy('products.id', in_array($sort, ['default', 'newest'], true) ? 'desc' : 'asc');
    }

    public function publishedProducts(): Builder
    {
        return Product::query()
            ->with('category')
            ->published();
    }

    public function productsForCategory(ProductCategory|int $category): Builder
    {
        $categoryId = $category instanceof ProductCategory
            ? $category->getKey()
            : $category;

        return $this->publishedProducts()
            ->where('product_category_id', $categoryId);
    }

    public function search(string $term): Builder
    {
        $term = trim($term);
        $products = $this->publishedProducts();

        if ($term === '') {
            return $products;
        }

        return $products->where(function (Builder $query) use ($term): void {
            $query
                ->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('content', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%");
        });
    }

    public function relatedProducts(Product $product, int $limit = 3): Collection
    {
        $limit = max(0, $limit);

        if ($limit === 0) {
            return collect();
        }

        if ($product->relatedProducts()->exists()) {
            return $product->relatedProducts()
                ->with(['category', 'media', 'mediaUsages.media'])
                ->published()
                ->limit($limit)
                ->get();
        }

        if (! $product->product_category_id) {
            return collect();
        }

        $legacyRelated = $this->productsForCategory($product->product_category_id)
            ->with(['media', 'mediaUsages.media'])
            ->whereKeyNot($product->getKey())
            ->orderBy('sort_order')
            ->latest('published_at')
            ->limit($limit)
            ->get();

        return $legacyRelated->values();
    }
}
