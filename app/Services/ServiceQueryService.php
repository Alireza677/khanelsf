<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ServiceQueryService
{
    public function publicDetailQuery(): Builder
    {
        return Service::query()
            ->with($this->contextRelations())
            ->published();
    }

    public function findPublishedBySlug(string $slug): ?Service
    {
        return $this->publicDetailQuery()
            ->where('slug', trim($slug))
            ->first();
    }

    public function findForAdminBySlug(string $slug): ?Service
    {
        return Service::query()
            ->with($this->contextRelations())
            ->where('slug', trim($slug))
            ->first();
    }

    public function archiveQuery(): Builder
    {
        return Service::query()
            ->with(['media', 'mediaUsages.media'])
            ->published()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    public function paginateArchive(int $perPage = 12): LengthAwarePaginator
    {
        return $this->archiveQuery()->paginate(max(1, min($perPage, 48)));
    }

    public function paginatePublicArchiveRoots(int $perPage = 12): LengthAwarePaginator
    {
        return $this->archiveQuery()
            ->whereNull('parent_id')
            ->paginate(max(1, min($perPage, 48)));
    }

    public function publicArchiveChildren(Collection $roots): Collection
    {
        $rootIds = $roots->modelKeys();

        if ($rootIds === []) {
            return collect();
        }

        return $this->archiveQuery()
            ->whereIn('parent_id', $rootIds)
            ->get();
    }

    public function publicDirectChildren(Service $service): Collection
    {
        return $this->archiveQuery()
            ->where('parent_id', $service->getKey())
            ->get();
    }

    public function publicAncestorChain(Service $service): Collection
    {
        $ancestors = collect();
        $parentId = $service->parent_id;
        $visited = [];

        while ($parentId !== null) {
            $parentId = (int) $parentId;

            if (isset($visited[$parentId])) {
                return collect();
            }

            $visited[$parentId] = true;
            $ancestor = Service::query()->published()->find($parentId);

            if (! $ancestor) {
                return collect();
            }

            $ancestors->prepend($ancestor);
            $parentId = $ancestor->parent_id;
        }

        return $ancestors->values();
    }

    public function prepareForContext(Service $service): Service
    {
        return $service->loadMissing($this->contextRelations());
    }

    public function relatedProjects(Service $service): Collection
    {
        $this->prepareForContext($service);

        return $service->getRelation('publicProjects')->values();
    }

    private function contextRelations(): array
    {
        return [
            'media',
            'mediaUsages.media',
            'publicProjects' => fn ($query) => $query->with([
                'category' => fn ($query) => $query->active(),
                'media',
                'mediaUsages.media',
            ]),
        ];
    }
}
