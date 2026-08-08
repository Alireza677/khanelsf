<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectDiscoveryVocabulary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ProjectGalleryDiscoveryService
{
    public function __construct(private readonly ModuleService $modules) {}

    /**
     * @param array<string, mixed> $requestedFilters
     * @return array{projects: LengthAwarePaginator, vocabularies: Collection, active_filters: array<string, array<int, string>>}
     */
    public function discover(array $requestedFilters, int $perPage): array
    {
        $vocabularies = $this->vocabularies();
        $activeFilters = $this->validatedFilters($requestedFilters, $vocabularies);

        $query = Project::query()
            ->with([
                'category',
                'media',
                'discoveryTerms' => fn ($query) => $query
                    ->active()
                    ->whereHas('vocabulary', fn (Builder $query): Builder => $query->active()),
            ])
            ->published()
            ->when(! $this->modules->projectsEnabled(), fn (Builder $query): Builder => $query->whereRaw('1 = 0'));

        foreach ($activeFilters as $vocabularySlug => $termSlugs) {
            $vocabulary = $vocabularies->firstWhere('slug', $vocabularySlug);
            $termIds = $vocabulary->terms
                ->whereIn('slug', $termSlugs)
                ->pluck('id');

            $query->whereHas('discoveryTerms', fn (Builder $query): Builder => $query
                ->whereIn('project_discovery_terms.id', $termIds)
                ->where('project_discovery_terms.is_active', true));
        }

        $projects = $query
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 60)))
            ->appends($activeFilters === [] ? [] : ['filters' => $activeFilters]);

        return [
            'projects' => $projects,
            'vocabularies' => $vocabularies,
            'active_filters' => $activeFilters,
        ];
    }

    private function vocabularies(): Collection
    {
        return ProjectDiscoveryVocabulary::query()
            ->active()
            ->with(['terms' => fn ($query) => $query
                ->active()
                ->orderBy('sort_order')
                ->orderBy('name')])
            ->whereHas('terms', fn (Builder $query): Builder => $query->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, array<int, string>> */
    private function validatedFilters(array $requested, Collection $vocabularies): array
    {
        $validated = [];

        foreach ($vocabularies as $vocabulary) {
            $values = $requested[$vocabulary->slug] ?? [];
            $values = is_array($values) ? $values : [$values];
            $allowed = $vocabulary->terms->pluck('slug')->all();
            $selected = collect($values)
                ->filter(fn (mixed $value): bool => is_string($value))
                ->map(fn (string $value): string => trim($value))
                ->filter(fn (string $value): bool => in_array($value, $allowed, true))
                ->unique()
                ->take(20)
                ->values()
                ->all();

            if ($selected !== []) {
                $validated[$vocabulary->slug] = $selected;
            }
        }

        return $validated;
    }
}
