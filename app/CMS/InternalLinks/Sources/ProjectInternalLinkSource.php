<?php

namespace App\CMS\InternalLinks\Sources;

use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\InternalLinks\Contracts\BrowsesInternalLinkTargets;
use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Contracts\ResolvesInternalLinkReference;
use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Support\InternalLinkSourceSupport;
use App\Models\Project;
use App\Services\ModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

final class ProjectInternalLinkSource implements BrowsesInternalLinkTargets, InternalLinkSearchSource, ResolvesInternalLinkReference
{
    use InternalLinkSourceSupport;

    public function __construct(private readonly ModuleService $modules) {}

    public function key(): string
    {
        return CoreActionType::Project->value;
    }

    public function label(): string
    {
        return 'پروژه';
    }

    public function isAvailable(): bool
    {
        return $this->modules->projectsEnabled() && Route::has('projects.show');
    }

    public function browse(string $query, int $page = 1, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $builder = Project::query()->published()
            ->when($filters['category_id'] ?? null, fn (Builder $builder, $category) => $builder->where('project_category_id', $category));

        return $this->paginateTargets($builder, 'title', $query, $page, $perPage)
            ->through(fn (Project $record): array => $this->browserRow(
                $record, $record->title, $record->resolveNavigationUrl(), ['details' => trim($record->client_name.' / '.$record->location, ' /')],
            ));
    }

    public function search(string $query, int $limit): array
    {
        if (! $this->isAvailable() || $limit < 1) {
            return [];
        }

        $contains = $this->containsPattern($query);
        $prefix = $this->prefixPattern($query);

        return Project::query()
            ->published()
            ->where(function (Builder $builder) use ($contains): void {
                $builder
                    ->where('title', 'like', $contains)
                    ->orWhere('slug', 'like', $contains);
            })
            ->orderByRaw(
                'CASE WHEN title = ? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END',
                [$query, $prefix],
            )
            ->orderBy('id')
            ->limit($this->boundedLimit($limit))
            ->get(['id', 'title', 'slug'])
            ->map(fn (Project $project) => $this->result(
                $this->key(),
                $project,
                $project->title,
                $project->resolveNavigationUrl(),
            ))
            ->filter()
            ->values()
            ->all();
    }

    public function find(int $referenceId): ?InternalLinkSearchResult
    {
        if ($referenceId <= 0 || ! $this->isAvailable()) {
            return null;
        }

        $project = Project::query()
            ->published()
            ->whereKey($referenceId)
            ->first(['id', 'title', 'slug']);

        return $project instanceof Project
            ? $this->result(
                $this->key(),
                $project,
                $project->title,
                $project->resolveNavigationUrl(),
            )
            : null;
    }
}
