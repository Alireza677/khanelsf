<?php

namespace App\CMS\InternalLinks\Sources;

use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Contracts\ResolvesInternalLinkReference;
use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Support\InternalLinkSourceSupport;
use App\Models\Project;
use App\Services\ModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

final class ProjectInternalLinkSource implements InternalLinkSearchSource, ResolvesInternalLinkReference
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
