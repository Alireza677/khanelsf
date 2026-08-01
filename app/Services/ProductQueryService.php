<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ProductQueryService
{
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
                ->with(['category', 'media'])
                ->published()
                ->limit($limit)
                ->get();
        }

        if (! $product->product_category_id) {
            return collect();
        }

        $legacyRelated = $this->productsForCategory($product->product_category_id)
            ->with('media')
            ->whereKeyNot($product->getKey())
            ->orderBy('sort_order')
            ->latest('published_at')
            ->limit($limit)
            ->get();

        return $legacyRelated->values();
    }
}
