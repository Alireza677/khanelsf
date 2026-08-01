<?php

namespace App\Services;

use App\Models\Menu;

class MenuService
{
    public function __construct(
        private readonly NavigationSourceVisibility $visibility,
        private readonly SettingsService $settings,
    ) {}

    public function byLocation(string $location): ?Menu
    {
        return $this->findActiveMenu('location', $location);
    }

    public function byId(int $id): ?Menu
    {
        return $this->findActiveMenu('id', $id);
    }

    public function header(): ?Menu
    {
        return $this->selectedMenu(
            $this->settings->headerMenuId(),
            'main',
        );
    }

    public function main(): ?Menu
    {
        return $this->header();
    }

    public function footer(): ?Menu
    {
        return $this->selectedMenu(
            $this->settings->footerMenuId(),
            'footer',
        );
    }

    private function selectedMenu(?int $selectedId, string $legacyLocation): ?Menu
    {
        return $selectedId
            ? $this->byId($selectedId)
            : $this->byLocation($legacyLocation);
    }

    private function findActiveMenu(string $column, int|string $value): ?Menu
    {
        $menu = rescue(
            fn () => Menu::query()
                ->where($column, $value)
                ->where('status', 'active')
                ->with([
                    'rootItems' => fn ($query) => $query
                        ->where('status', 'active')
                        ->with(['children' => fn ($query) => $query
                            ->where('status', 'active')
                            ->orderBy('sort_order')
                            ->orderBy('title')])
                        ->orderBy('sort_order')
                        ->orderBy('title'),
                ])
                ->first(),
            null,
            report: false,
        );

        if (! $menu) {
            return null;
        }

        $menu->setRelation('rootItems', $this->visibleItems($menu->rootItems));

        return $menu;
    }

    private function visibleItems($items)
    {
        return $items
            ->filter(fn ($item): bool => $this->visibility->menuItemIsVisible($item))
            ->map(function ($item) {
                $item->setRelation('children', $this->visibleItems($item->children));

                return $item;
            })
            ->values();
    }
}
