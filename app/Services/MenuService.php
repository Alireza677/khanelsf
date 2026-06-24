<?php

namespace App\Services;

use App\Models\Menu;

class MenuService
{
    public function __construct(private readonly ModuleService $modules) {}

    public function byLocation(string $location): ?Menu
    {
        $menu = rescue(
            fn () => Menu::query()
                ->where('location', $location)
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

    public function main(): ?Menu
    {
        return $this->byLocation('main');
    }

    public function footer(): ?Menu
    {
        return $this->byLocation('footer');
    }

    private function visibleItems($items)
    {
        return $items
            ->filter(fn ($item): bool => $this->modules->urlIsVisible($item->url))
            ->map(function ($item) {
                $item->setRelation('children', $this->visibleItems($item->children));

                return $item;
            })
            ->values();
    }
}
