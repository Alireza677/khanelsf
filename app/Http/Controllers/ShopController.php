<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ModuleService;
use App\Services\ProductTemplateRuntime;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\ShopArchiveService;
use App\Services\ShopCategoryTree;
use App\Services\ShopFilterParser;
use App\Services\TemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShopController extends Controller
{
    public function index(Request $request): View|Response|RedirectResponse
    {
        return $this->archive($request);
    }

    public function category(string $slug, Request $request): View|Response|RedirectResponse
    {
        return $this->archive($request, $slug);
    }

    private function archive(Request $request, ?string $slug = null): View|Response|RedirectResponse
    {
        $this->abortIfShopDisabled(app(ModuleService::class));
        $tree = app(ShopCategoryTree::class);
        // A hidden route must never be made public by a different query parameter.
        abort_if($slug !== null && ! $tree->category($slug), 404);
        $filters = app(ShopFilterParser::class)->parse($request->query(), $slug);
        $archive = app(ShopArchiveService::class);
        $selected = $archive->validate($filters, $tree);
        if ($request->getSchemeAndHttpHost().$request->getRequestUri() !== $filters->url(withPage: true)) {
            return redirect()->to($filters->url(withPage: true), 301);
        }
        $settings = app(SettingsService::class);
        $templates = app(TemplateService::class);
        $seoService = app(SeoService::class);
        $favoriteProductIds = $this->favoriteProductIds($request);
        $data = $archive->viewData($filters, $tree, $selected, $favoriteProductIds, (int) $settings->get('shop_per_page', 12));
        $category = $data['activeCategory'];
        $data['seo'] = $category ? $seoService->forProductCategory($category, $filters) : $seoService->forShopIndex($filters);
        $template = $templates->findTemplateFor($category ? 'product_category' : 'shop_index', $category);
        $view = $templates->viewOrFallback($template, $category ? 'shop.category' : 'shop.index', $data);

        return $data['status'] === 200 ? $view : response($view, $data['status']);
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

    public function show(
        string $slug,
        ModuleService $modules,
        ProductTemplateRuntime $runtime,
    ): View {
        $this->abortIfShopDisabled($modules);

        $product = Product::query()
            ->with('category')
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        return $runtime->render($product);
    }

    private function abortIfShopDisabled(ModuleService $modules): void
    {
        abort_unless($modules->shopEnabled(), 404);
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
