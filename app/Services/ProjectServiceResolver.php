<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Service;
use Illuminate\Support\Collection;

final class ProjectServiceResolver
{
    /**
     * @return Collection<int, array{label: string, url: string|null}>
     */
    public function items(Project $project, bool $publicLinksEnabled = true): Collection
    {
        $relatedServices = $project->relationLoaded('relatedServices')
            ? $project->getRelation('relatedServices')
            : $project->relatedServices()->published()->get();

        $canonicalItems = $relatedServices
            ->filter(fn (mixed $service): bool => $service instanceof Service && $service->isPublished())
            ->map(fn (Service $service): array => [
                'label' => $service->name,
                'url' => $publicLinksEnabled ? $service->resolveNavigationUrl() : null,
            ])
            ->filter(fn (array $item): bool => filled($item['label']))
            ->values();

        if ($canonicalItems->isNotEmpty()) {
            return $canonicalItems;
        }

        return $this->legacyNames($project)
            ->map(fn (string $name): array => ['label' => $name, 'url' => null])
            ->values();
    }

    public function names(Project $project): Collection
    {
        return $this->items($project)->pluck('label')->values();
    }

    private function legacyNames(Project $project): Collection
    {
        return collect($project->services)
            ->map(fn (mixed $service): ?string => $this->stringOrNull(
                is_array($service) ? ($service['name'] ?? null) : $service,
            ))
            ->filter()
            ->values();
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
