<?php

namespace App\Services;

use App\Models\ProductAttribute;
use App\Models\ProductBrand;
use App\Models\ProductTag;
use App\Shop\ShopFilters;
use Illuminate\Database\Eloquent\Builder;

final class ShopArchiveService
{
    public function __construct(private readonly ProductQueryService $products) {}

    /** Shared view contract; callers retain their own authorization and SEO rules. */
    public function viewData(ShopFilters $filters, ShopCategoryTree $tree, array $selected, array $favoriteIds, int $perPage): array
    {
        $result = $this->build($filters, $tree, $selected, $favoriteIds, $perPage);
        $settings = app(SettingsService::class);
        $category = $tree->category($filters->data['category']);
        $heading = $category?->name ?? $settings->get('shop_index_title', 'فروشگاه');
        $description = $category?->description ?? $settings->get('shop_index_description', 'محصولات موجود را مشاهده کنید.');
        $emptyMessage = 'محصولی با شرایط انتخاب‌شده پیدا نشد؛ فیلترها را تغییر دهید یا پاک کنید.';

        return [
            'status' => $result['status'],
            'products' => $result['products'], 'categories' => $tree->visible(), 'activeCategory' => $category,
            'heading' => $heading, 'description' => $description, 'emptyMessage' => $emptyMessage,
            'filters' => $filters->data, 'filterUi' => $result['filterUi'],
            'templateContext' => [
                'kind' => 'archive', 'type' => 'products', 'items' => $result['products'],
                'categories' => $tree->visible(), 'activeCategory' => $category, 'category' => $category,
                'heading' => $heading, 'description' => $description, 'emptyMessage' => $emptyMessage,
                'filters' => $filters->data, 'filterUi' => $result['filterUi'], 'favoriteProductIds' => $favoriteIds,
            ],
        ];
    }

    public function validate(ShopFilters $filters, ShopCategoryTree $tree): array
    {
        $data = $filters->data;
        abort_if($data['category'] !== '' && ! $tree->category($data['category']), 404);
        $brands = $data['brands'] === [] ? collect() : ProductBrand::query()->where('status', 'active')->whereIn('slug', $data['brands'])->get();
        $tags = $data['tags'] === [] ? collect() : ProductTag::query()->whereIn('slug', $data['tags'])->get();
        $attributes = $data['attributes'] === [] ? collect() : ProductAttribute::query()->where('status', 'active')->where('is_filterable', true)
            ->where('data_type', 'choice')->whereIn('slug', array_keys($data['attributes']))
            ->with(['values' => fn ($query) => $query->where('status', 'active')->whereIn('slug', array_unique(array_merge(...array_values($data['attributes']))))])->get();
        abort_unless($brands->count() === count($data['brands']) && $tags->count() === count($data['tags']) && $attributes->count() === count($data['attributes']), 404);
        foreach ($attributes as $attribute) {
            abort_unless($attribute->values->whereIn('slug', $data['attributes'][$attribute->slug])->count() === count($data['attributes'][$attribute->slug]), 404);
        }

        return compact('brands', 'tags', 'attributes');
    }

    public function build(ShopFilters $filters, ShopCategoryTree $tree, array $selected, array $favoriteIds, int $perPage): array
    {
        $query = $this->products->archive($filters, $tree, $favoriteIds);
        $products = $this->products->sortArchive($query, $filters->data['sort'])
            ->with(['media', 'mediaUsages.media'])
            ->paginate(max(1, min(48, $perPage)), ['products.*'], 'page', $filters->page)
            ->withPath($filters->baseUrl())->appends($filters->query());
        // Facets use category/search/price/stock/flags, before brand/tag/attribute selections.
        // This keeps OR alternatives available without one query for every facet option.
        $base = $this->products->archive($filters, $tree, $favoriteIds, facets: true)->setEagerLoads([])->select('products.id');
        $hasProducts = fn (Builder $query) => $query->whereIn('products.id', clone $base);
        $hasAssignments = fn (Builder $query) => $query->whereIn('product_id', clone $base);
        $values = fn ($query) => $query->where('status', 'active')->whereHas('assignments', $hasAssignments);
        $facets = [
            'brands' => ProductBrand::query()->where('status', 'active')->whereHas('products', $hasProducts)->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => ProductTag::query()->whereHas('products', $hasProducts)->orderBy('name')->get(),
            'attributes' => ProductAttribute::query()->where('status', 'active')->where('is_filterable', true)->where('data_type', 'choice')
                ->whereHas('values', $values)->with(['values' => $values])->orderBy('sort_order')->orderBy('name')->get(),
        ];
        $categoryUrls = [];
        $categoryLabels = [];
        $categoriesById = $tree->visible()->keyBy('id');
        foreach ($tree->visible() as $category) {
            $categoryUrls[$category->id] = $filters->with(['category' => $category->slug])->url();
            $path = [$category->name];
            $parent = $categoriesById->get($category->parent_id);
            while ($parent) {
                array_unshift($path, $parent->name);
                $parent = $categoriesById->get($parent->parent_id);
            }
            $categoryLabels[$category->id] = implode(' / ', $path);
        }
        $filterHidden = $filters->hiddenInputs(['q', 'brands', 'tags', 'attributes', 'min_price', 'max_price', 'stock', 'featured', 'on_sale', 'include_children']);
        foreach (['brands', 'tags'] as $key) {
            foreach (array_diff($filters->data[$key], $facets[$key]->pluck('slug')->all()) as $value) {
                $filterHidden[] = ['name' => $key.'[]', 'value' => $value];
            }
        }
        foreach ($filters->data['attributes'] as $slug => $selectedValues) {
            $available = $facets['attributes']->firstWhere('slug', $slug)?->values->pluck('slug')->all() ?? [];
            foreach (array_diff($selectedValues, $available) as $value) {
                $filterHidden[] = ['name' => 'attributes['.$slug.'][]', 'value' => $value];
            }
        }

        return [
            'products' => $products,
            'status' => $filters->page > $products->lastPage() ? 404 : 200,
            'filterUi' => [
                'facets' => $facets,
                'action' => $filters->baseUrl(),
                'clearUrl' => $filters->baseUrl(),
                'categoryUrls' => $categoryUrls,
                'categoryLabels' => $categoryLabels,
                'allCategoriesUrl' => $filters->with(['category' => '', 'include_children' => true])->url(),
                'favoriteUrl' => $filters->with(['favorites' => ! $filters->data['favorites']])->url(),
                'searchHidden' => $filters->hiddenInputs(['q']),
                'sortHidden' => $filters->hiddenInputs(['sort']),
                'filterHidden' => $filterHidden,
                'sortOptions' => ShopFilters::SORTS,
                'active' => $this->activeFilters($filters, $tree, $selected),
                'isFiltered' => $filters->isFiltered(),
            ],
        ];
    }

    private function activeFilters(ShopFilters $filters, ShopCategoryTree $tree, array $selected): array
    {
        $active = [];
        $add = function (string $label, array $changes) use (&$active, $filters): void {
            $active[] = ['label' => $label, 'url' => $filters->with($changes)->url()];
        };
        if ($category = $tree->category($filters->data['category'])) {
            $add('دسته‌بندی: '.$category->name, ['category' => '', 'include_children' => true]);
        }
        foreach (['q' => 'جستجو', 'min_price' => 'حداقل قیمت', 'max_price' => 'حداکثر قیمت', 'stock' => 'موجودی'] as $key => $label) {
            $value = $filters->data[$key];
            if ($value !== null && $value !== '') {
                $labelValue = $key === 'stock' ? ($value === 'in_stock' ? 'موجود' : 'ناموجود') : $value;
                $add($label.': '.$labelValue, [$key => in_array($key, ['min_price', 'max_price'], true) ? null : '']);
            }
        }
        foreach (['featured' => 'محصولات ویژه', 'on_sale' => 'محصولات تخفیف‌دار', 'favorites' => 'علاقه‌مندی‌ها'] as $key => $label) {
            if ($filters->data[$key]) {
                $add($label, [$key => false]);
            }
        }
        if (! $filters->data['include_children']) {
            $add('فقط همین دسته؛ بدون زیرشاخه‌ها', ['include_children' => true]);
        }
        if ($filters->data['sort'] !== 'default') {
            $add('مرتب‌سازی: '.ShopFilters::SORTS[$filters->data['sort']], ['sort' => 'default']);
        }
        foreach (['brands' => 'برند', 'tags' => 'برچسب'] as $key => $label) {
            foreach ($selected[$key] as $item) {
                $add($label.': '.$item->name, [$key => array_values(array_diff($filters->data[$key], [$item->slug]))]);
            }
        }
        foreach ($selected['attributes'] as $attribute) {
            foreach ($attribute->values->whereIn('slug', $filters->data['attributes'][$attribute->slug]) as $value) {
                $attributes = $filters->data['attributes'];
                $attributes[$attribute->slug] = array_values(array_diff($attributes[$attribute->slug], [$value->slug]));
                if ($attributes[$attribute->slug] === []) {
                    unset($attributes[$attribute->slug]);
                }
                $add($attribute->name.': '.$value->name.($attribute->unit ? ' '.$attribute->unit : ''), ['attributes' => $attributes]);
            }
        }

        return $active;
    }
}
