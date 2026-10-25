<?php

namespace App\CMS\InternalLinks\Sources;

use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\InternalLinks\Contracts\BrowsesInternalLinkTargets;
use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Contracts\ResolvesInternalLinkReference;
use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Support\InternalLinkSourceSupport;
use App\Models\Service;
use App\Services\ServiceSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;

final class ServiceInternalLinkSource implements BrowsesInternalLinkTargets, InternalLinkSearchSource, ResolvesInternalLinkReference
{
    use InternalLinkSourceSupport;

    public function __construct(private readonly ServiceSettings $settings) {}

    public function key(): string
    {
        return CoreActionType::Service->value;
    }

    public function label(): string
    {
        return 'خدمت';
    }

    public function isAvailable(): bool
    {
        return $this->settings->publicEnabled() && Route::has('services.show');
    }

    public function browse(string $query, int $page = 1, int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $results = $this->paginateTargets(Service::query()->published(), 'name', $query, $page, $perPage);
        $ancestors = $results->getCollection()->keyBy('id');
        $frontier = $ancestors;

        // Only ancestors of this page are loaded, in batches; malformed legacy cycles terminate.
        while ($frontier->isNotEmpty()) {
            $parentIds = $frontier->pluck('parent_id')->filter()->unique()->diff($ancestors->keys());
            if ($parentIds->isEmpty()) {
                break;
            }
            $frontier = Service::query()->whereKey($parentIds)->get(['id', 'name', 'parent_id'])->keyBy('id');
            $ancestors = $ancestors->union($frontier);
        }

        return $results->through(function (Service $record) use ($ancestors): array {
            $names = [];
            $seen = [$record->getKey() => true];
            $parent = $ancestors->get($record->parent_id);
            while ($parent && ! isset($seen[$parent->getKey()])) {
                $seen[$parent->getKey()] = true;
                array_unshift($names, $parent->name);
                $parent = $ancestors->get($parent->parent_id);
            }

            return $this->browserRow($record, $record->name, $record->resolveNavigationUrl(), ['details' => implode(' / ', $names)]);
        });
    }

    public function search(string $query, int $limit): array
    {
        if (! $this->isAvailable() || $limit < 1) {
            return [];
        }

        $contains = $this->containsPattern($query);
        $prefix = $this->prefixPattern($query);

        return Service::query()
            ->published()
            ->where(function (Builder $builder) use ($contains): void {
                $builder
                    ->where('name', 'like', $contains)
                    ->orWhere('slug', 'like', $contains);
            })
            ->orderByRaw(
                'CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
                [$query, $prefix],
            )
            ->orderBy('id')
            ->limit($this->boundedLimit($limit))
            ->get(['id', 'name', 'slug', 'status', 'published_at'])
            ->map(fn (Service $service) => $this->result(
                $this->key(),
                $service,
                $service->name,
                $service->resolveNavigationUrl(),
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

        $service = Service::query()
            ->published()
            ->whereKey($referenceId)
            ->first(['id', 'name', 'slug', 'status', 'published_at']);

        return $service instanceof Service
            ? $this->result(
                $this->key(),
                $service,
                $service->name,
                $service->resolveNavigationUrl(),
            )
            : null;
    }
}
