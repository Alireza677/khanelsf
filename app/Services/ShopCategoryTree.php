<?php

namespace App\Services;

use App\Models\ProductCategory;
use Illuminate\Support\Collection;

/** Request-local category metadata, never an in-memory product filter. */
final class ShopCategoryTree
{
    private ?Collection $visible = null;

    public function visible(): Collection
    {
        if ($this->visible !== null) {
            return $this->visible;
        }
        $all = ProductCategory::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->get()->keyBy('id');
        $visible = $all->filter(function (ProductCategory $category) use ($all): bool {
            $seen = [];
            while ($category) {
                if ($category->status !== 'active' || isset($seen[$category->id])) {
                    return false;
                }
                $seen[$category->id] = true;
                if ($category->parent_id !== null && ! $all->has($category->parent_id)) {
                    return false;
                }
                $category = $all->get($category->parent_id);
            }

            return true;
        });

        return $this->visible = $visible->values();
    }

    public function ids(): array
    {
        return $this->visible()->pluck('id')->all();
    }

    public function category(string $slug): ?ProductCategory
    {
        return $slug !== '' ? $this->visible()->firstWhere('slug', $slug) : null;
    }

    public function subtree(ProductCategory $category, bool $includeChildren = true): array
    {
        $ids = [$category->id];
        if ($includeChildren) {
            $children = $this->visible()->groupBy('parent_id');
            for ($index = 0; $index < count($ids); $index++) {
                foreach ($children->get($ids[$index], []) as $child) {
                    $ids[] = $child->id;
                }
            }
        }

        return $ids;
    }
}
