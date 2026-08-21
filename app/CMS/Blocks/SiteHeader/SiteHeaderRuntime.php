<?php

namespace App\CMS\Blocks\SiteHeader;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\Models\Menu;
use App\Services\CartService;
use App\Services\MenuService;
use App\Services\ModuleService;
use App\Services\PublicAccountNavigation;
use App\Services\PublicSearchService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class SiteHeaderRuntime
{
    public function __construct(
        private readonly SiteHeaderDataNormalizer $normalizer,
        private readonly RuntimeActionResolver $actions,
        private readonly ActionPresentation $presentation,
        private readonly SettingsService $settings,
        private readonly MenuService $menus,
        private readonly PublicAccountNavigation $accounts,
        private readonly ModuleService $modules,
        private readonly CartService $cart,
        private readonly PublicSearchService $search,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function prepare(array $data, array $context = [], bool $preview = false): array
    {
        $header = $this->normalizer->normalize($data);
        $menuId = $header['settings']['menu_id'];
        $menu = $menuId
            ? $this->menus->byId($menuId)
            : $this->menus->header();
        $buttons = [
            ...$header['content']['top_actions'],
            $header['content']['primary_action'],
        ];
        $destinations = array_map(
            static fn (array $button): ActionDestination => ActionDestination::fromArray(
                is_array($button['action'] ?? null) ? $button['action'] : [],
            ),
            $buttons,
        );
        $resolved = $this->actions->resolveMany(
            $destinations,
            new ResolutionContext(
                $preview ? ResolutionMode::Preview : ResolutionMode::Production,
            ),
        );
        $actionContext = [
            'page_id' => $context['page_id'] ?? null,
            'page_url' => $context['page_url'] ?? null,
            'block_id' => $header['block_id'],
        ];

        foreach ($buttons as $index => $button) {
            $buttons[$index]['presentation'] = $this->presentation->present(
                $resolved[$index],
                $actionContext,
            );
        }

        $navigationId = 'industrial-navigation-'.Str::lower(
            preg_replace('/[^a-zA-Z0-9]+/', '-', $header['block_id'] ?: 'global'),
        );

        $cart = null;

        if ($this->modules->shopEnabled() && Route::has('cart.index')) {
            $cartCount = $this->cart->count();
            $cartItems = $this->cart->items();
            $cart = [
                'url' => route('cart.index', absolute: false),
                'remove_url' => Route::has('cart.remove')
                    ? route('cart.remove', absolute: false)
                    : null,
                'checkout_url' => Route::has('checkout.create')
                    ? route('checkout.create', absolute: false)
                    : null,
                'shop_url' => Route::has('shop.index')
                    ? route('shop.index', absolute: false)
                    : null,
                'count' => $cartCount,
                'badge' => $cartCount > 99 ? '99+' : (string) $cartCount,
                'items' => $cartItems->map(static fn (array $item): array => [
                    ...$item,
                    'product_url' => Route::has('shop.show') && filled($item['slug'] ?? null)
                        ? route('shop.show', $item['slug'], absolute: false)
                        : null,
                ])->all(),
                'subtotal' => (float) $cartItems->sum('total'),
                'label' => $cartCount > 0
                    ? "سبد خرید، {$cartCount} کالا"
                    : 'سبد خرید',
            ];
        }

        return [
            'block_id' => $header['block_id'],
            'site_name' => $this->settings->siteName(),
            'logo_url' => $this->settings->logoUrl(),
            'home_url' => Route::has('home') ? route('home', absolute: false) : '/',
            'search_url' => $header['settings']['search_enabled'] && Route::has('search.index')
                ? route('search.index', absolute: false)
                : null,
            'search_sources' => $header['settings']['search_enabled'] && Route::has('search.index')
                ? $this->search->availableSources()
                : [],
            'cart' => $cart,
            'navigation_id' => trim($navigationId, '-'),
            'overlay_ids' => [
                'cart' => trim($navigationId, '-').'-cart-drawer',
                'search' => trim($navigationId, '-').'-search-modal',
            ],
            'navigation' => $this->navigation($menu),
            'settings' => $header['settings'],
            'top_actions' => array_slice($buttons, 0, 2),
            'primary_action' => $buttons[2],
            'account' => $this->accounts->present(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function navigation(?Menu $menu): array
    {
        if (! $menu?->relationLoaded('rootItems')) {
            return [];
        }

        return $this->navigationItems($menu->rootItems->all(), 1);
    }

    /** @return array<int, array<string, mixed>> */
    private function navigationItems(array $items, int $depth): array
    {
        if ($depth > 2) {
            return [];
        }

        $navigation = [];

        foreach ($items as $item) {
            $url = $item->resolvedUrl();

            if (blank($url)) {
                continue;
            }

            $navigation[] = [
                'label' => $item->title,
                'url' => $url,
                'target' => $item->target === '_blank' ? '_blank' : '_self',
                'children' => $this->navigationItems(
                    $item->relationLoaded('children') ? $item->children->all() : [],
                    $depth + 1,
                ),
            ];
        }

        return $navigation;
    }
}
