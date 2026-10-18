<?php

namespace App\Services;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\Models\MenuItem;

class NavigationSourceVisibility
{
    public function __construct(
        private readonly NavigationSourceRegistry $sources,
        private readonly ModuleService $modules,
    ) {}

    public function sourceIsVisible(?string $sourceKey): bool
    {
        return $this->sources->isAvailable($sourceKey);
    }

    public function canAdd(?string $type, ?string $url = null): bool
    {
        return $type !== MenuItem::TYPE_SOURCE
            && $this->modules->urlIsVisible($url);
    }

    public function canAddSource(?string $sourceKey): bool
    {
        return $this->sourceIsVisible($sourceKey);
    }

    public function menuItemIsVisible(MenuItem $item): bool
    {
        return $item->status === 'active' && $this->destinationIsVisible($item);
    }

    public function destinationIsVisible(MenuItem $item): bool
    {
        if (filled($item->source_key) || $item->type === MenuItem::TYPE_SOURCE) {
            return filled($item->source_key)
                && $this->sourceIsVisible($item->source_key)
                && filled($item->resolvedUrl());
        }

        $url = $item->resolvedUrl();

        return ($item->type === MenuItem::TYPE_CUSTOM_URL || filled($url))
            && $this->canAdd($item->type, $url);
    }

    /**
     * @return array<int, array{source_key: string, label: string, module: string|null, url: string|null}>
     */
    public function visibleSources(): array
    {
        return collect($this->sources->available())
            ->map(fn ($source): array => $source->toArray())
            ->values()
            ->all();
    }
}
