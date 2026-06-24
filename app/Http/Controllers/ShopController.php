<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ModuleService;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\TemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request, SeoService $seoService, SettingsService $settings, ModuleService $modules, TemplateService $templates): View
    {
        $this->abortIfShopDisabled($modules);

        $filters = $this->shopFilters($request);
        $categories = $this->shopCategories();
        $activeCategory = $filters['category'] !== ''
            ? $categories->firstWhere('slug', $filters['category'])
            : null;
        $favoriteProductIds = $this->favoriteProductIds($request);

        $products = Product::query()
            ->with('category')
            ->published()
            ->when($filters['favorites'], fn ($query) => $query->whereKey($favoriteProductIds))
            ->when($activeCategory, fn ($query) => $query->whereBelongsTo($activeCategory, 'category'))
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query
                        ->where('title', 'like', "%{$filters['q']}%")
                        ->orWhere('excerpt', 'like', "%{$filters['q']}%")
                        ->orWhere('content', 'like', "%{$filters['q']}%")
                        ->orWhere('sku', 'like', "%{$filters['q']}%");
                });
            })
            ->when($filters['min_price'] !== null, fn ($query) => $query->where('price', '>=', $filters['min_price']))
            ->when($filters['max_price'] !== null, fn ($query) => $query->where('price', '<=', $filters['max_price']))
            ->when($filters['stock'] === 'in_stock', fn ($query) => $query->where('has_stock', true)->where('stock_status', 'in_stock'))
            ->when($filters['stock'] === 'out_of_stock', fn ($query) => $query->where(function ($query): void {
                $query->where('has_stock', false)->orWhere('stock_status', '!=', 'in_stock');
            }))
            ->when($filters['featured'], fn ($query) => $query->featured())
            ->orderBy('sort_order')
            ->latest('published_at')
            ->paginate((int) $settings->get('shop_per_page', 12))
            ->withQueryString();

        $template = $templates->findTemplateFor('shop_index');

        return $templates->viewOrFallback($template, 'shop.index', [
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'heading' => $settings->get('shop_index_title', 'فروشگاه'),
            'description' => $settings->get('shop_index_description', 'محصولات موجود را مشاهده کنید.'),
            'seo' => $seoService->forShopIndex(),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'products',
                'items' => $products,
                'categories' => $categories,
                'activeCategory' => $activeCategory,
                'heading' => $settings->get('shop_index_title', 'فروشگاه'),
                'description' => $settings->get('shop_index_description', 'محصولات موجود را مشاهده کنید.'),
                'emptyMessage' => 'هنوز محصولی منتشر نشده است.',
                'filters' => $filters,
                'favoriteProductIds' => $favoriteProductIds,
            ],
        ]);
    }

    public function category(string $slug, Request $request, SeoService $seoService, SettingsService $settings, ModuleService $modules, TemplateService $templates): View
    {
        $this->abortIfShopDisabled($modules);

        $category = ProductCategory::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        $filters = $this->shopFilters($request);
        $categories = $this->shopCategories();
        $favoriteProductIds = $this->favoriteProductIds($request);

        $products = Product::query()
            ->with('category')
            ->published()
            ->when($filters['favorites'], fn ($query) => $query->whereKey($favoriteProductIds))
            ->whereBelongsTo($category, 'category')
            ->when($filters['q'] !== '', function ($query) use ($filters): void {
                $query->where(function ($query) use ($filters): void {
                    $query
                        ->where('title', 'like', "%{$filters['q']}%")
                        ->orWhere('excerpt', 'like', "%{$filters['q']}%")
                        ->orWhere('content', 'like', "%{$filters['q']}%")
                        ->orWhere('sku', 'like', "%{$filters['q']}%");
                });
            })
            ->when($filters['min_price'] !== null, fn ($query) => $query->where('price', '>=', $filters['min_price']))
            ->when($filters['max_price'] !== null, fn ($query) => $query->where('price', '<=', $filters['max_price']))
            ->when($filters['stock'] === 'in_stock', fn ($query) => $query->where('has_stock', true)->where('stock_status', 'in_stock'))
            ->when($filters['stock'] === 'out_of_stock', fn ($query) => $query->where(function ($query): void {
                $query->where('has_stock', false)->orWhere('stock_status', '!=', 'in_stock');
            }))
            ->when($filters['featured'], fn ($query) => $query->featured())
            ->orderBy('sort_order')
            ->latest('published_at')
            ->paginate((int) $settings->get('shop_per_page', 12))
            ->withQueryString();

        $template = $templates->findTemplateFor('product_category', $category);

        return $templates->viewOrFallback($template, 'shop.category', [
            'products' => $products,
            'categories' => $categories,
            'heading' => $category->name,
            'description' => $category->description,
            'activeCategory' => $category,
            'emptyMessage' => 'هنوز محصولی در این دسته‌بندی منتشر نشده است.',
            'seo' => $seoService->forProductCategory($category),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'products',
                'items' => $products,
                'categories' => $categories,
                'category' => $category,
                'activeCategory' => $category,
                'heading' => $category->name,
                'description' => $category->description,
                'emptyMessage' => 'هنوز محصولی در این دسته‌بندی منتشر نشده است.',
                'filters' => [
                    ...$filters,
                    'category' => $category->slug,
                ],
                'favoriteProductIds' => $favoriteProductIds,
            ],
        ]);
    }

    public function toggleFavorite(Product $product, Request $request): RedirectResponse
    {
        $favoriteProductIds = $this->favoriteProductIds($request);

        if (in_array($product->getKey(), $favoriteProductIds, true)) {
            $favoriteProductIds = array_values(array_diff($favoriteProductIds, [$product->getKey()]));
        } else {
            $favoriteProductIds[] = $product->getKey();
        }

        $request->session()->put('shop.favorite_product_ids', array_values(array_unique($favoriteProductIds)));

        return back();
    }

    public function show(string $slug, SeoService $seoService, SettingsService $settings, ModuleService $modules, TemplateService $templates): View
    {
        $this->abortIfShopDisabled($modules);

        $product = Product::query()
            ->with('category')
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        $relatedProducts = Product::query()
            ->with('category')
            ->published()
            ->whereKeyNot($product->getKey())
            ->when($product->product_category_id, fn ($query) => $query->where('product_category_id', $product->product_category_id))
            ->orderBy('sort_order')
            ->latest('published_at')
            ->take(3)
            ->get();

        $template = $templates->findTemplateFor('product_single', $product);

        return $templates->viewOrFallback($template, 'shop.show', [
            'product' => $product,
            'relatedProducts' => $relatedProducts,
            'seo' => $seoService->forProduct($product),
            'templateContext' => [
                'kind' => 'single',
                'type' => 'product',
                'model' => $product,
                'related' => $relatedProducts,
            ],
        ]);
    }

    private function abortIfShopDisabled(ModuleService $modules): void
    {
        abort_unless($modules->shopEnabled(), 404);
    }

    private function shopCategories()
    {
        return ProductCategory::query()
            ->active()
            ->withCount(['products' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function shopFilters(Request $request): array
    {
        $minPrice = $request->filled('min_price') ? max(0, (float) $request->query('min_price')) : null;
        $maxPrice = $request->filled('max_price') ? max(0, (float) $request->query('max_price')) : null;
        $thicknesses = collect((array) $request->query('thickness', []))
            ->map(fn ($value): string => trim((string) $value))
            ->intersect(['0.75', '0.9', '1.25', '1.5'])
            ->values()
            ->all();
        $applications = collect((array) $request->query('application', []))
            ->map(fn ($value): string => trim((string) $value))
            ->intersect(['wall', 'roof', 'truss', 'connections', 'insulation'])
            ->values()
            ->all();

        if ($minPrice !== null && $maxPrice !== null && $minPrice > $maxPrice) {
            [$minPrice, $maxPrice] = [$maxPrice, $minPrice];
        }

        return [
            'q' => trim((string) $request->query('q', '')),
            'category' => trim((string) $request->query('category', '')),
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'stock' => in_array($request->query('stock'), ['in_stock', 'out_of_stock'], true)
                ? $request->query('stock')
                : '',
            'featured' => $request->boolean('featured'),
            'favorites' => $request->boolean('favorites'),
            // TODO: Apply these filters to the product query after corresponding product fields are added.
            'thickness' => $thicknesses,
            'application' => $applications,
        ];
    }

    private function favoriteProductIds(Request $request): array
    {
        return collect($request->session()->get('shop.favorite_product_ids', []))
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
