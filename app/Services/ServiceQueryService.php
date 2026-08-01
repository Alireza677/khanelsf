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
            ->with('media')
            ->published()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    public function paginateArchive(int $perPage = 12): LengthAwarePaginator
    {
        return $this->archiveQuery()->paginate(max(1, min($perPage, 48)));
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
            'publicProjects' => fn ($query) => $query->with([
                'category' => fn ($query) => $query->active(),
                'media',
            ]),
        ];
    }
}
