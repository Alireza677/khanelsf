<?php

namespace App\CMS\Blocks\SiteHeader;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Presentation\ActionPresentation;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\Models\Menu;
use App\Services\MenuService;
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

        return [
            'block_id' => $header['block_id'],
            'site_name' => $this->settings->siteName(),
            'logo_url' => $this->settings->logoUrl(),
            'home_url' => Route::has('home') ? route('home', absolute: false) : '/',
            'search_url' => $header['settings']['search_enabled'] && Route::has('blog.search')
                ? route('blog.search', absolute: false)
                : null,
            'navigation_id' => trim($navigationId, '-'),
            'navigation' => $this->navigation($menu),
            'settings' => $header['settings'],
            'top_actions' => array_slice($buttons, 0, 2),
            'primary_action' => $buttons[2],
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
