<?php

namespace App\CMS\Navigation\Sources;

use App\CMS\InternalLinks\Sources\ServiceInternalLinkSource;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Services-owned entity picker; system destinations remain in NavigationSourceRegistry.
 */
final class ServiceNavigationSource
{
    private const PER_PAGE = 30;

    private const COLUMNS = ['id', 'parent_id', 'name', 'slug', 'status', 'published_at'];

    public function __construct(private readonly ServiceInternalLinkSource $links) {}

    public function isAvailable(): bool
    {
        return $this->links->isAvailable();
    }

    public function selected(array $ids): Collection
    {
        if (! $this->isAvailable()) {
            return new Collection;
        }

        return $this->publicQuery()->whereKey($ids)->get(self::COLUMNS)
            ->filter(fn (Service $service): bool => filled($service->resolveNavigationUrl()))
            ->sortBy(fn (Service $service): int => array_search((int) $service->getKey(), $ids, true))
            ->values();
    }

    /** Only expanded branches are fetched, with a bounded page of siblings each. */
    public function browse(array $expandedIds, array $pages): array
    {
        if (! $this->isAvailable()) {
            return $this->emptyGroup();
        }

        return $this->branch(null, array_fill_keys(array_map('intval', $expandedIds), true), $pages);
    }

    /** Search globally, including deep descendants, and fetch their public ancestors in batches. */
    public function search(string $search, int $page): array
    {
        if (! $this->isAvailable()) {
            return $this->emptyGroup();
        }

        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr(trim($search), 0, 200)).'%';
        $results = $this->orderedQuery()
            ->where(fn (Builder $query) => $query
                ->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("slug LIKE ? ESCAPE '!'", [$pattern]))
            ->simplePaginate(self::PER_PAGE, self::COLUMNS, 'serviceSearchPage', max(1, $page));

        $services = $results->getCollection()->keyBy('id');
        $frontier = $services;

        while ($frontier->isNotEmpty()) {
            $parentIds = $frontier->pluck('parent_id')->filter()->unique()->diff($services->keys());
            if ($parentIds->isEmpty()) {
                break;
            }

            $frontier = $this->publicQuery()->whereKey($parentIds)->get(self::COLUMNS)->keyBy('id');
            $services = $services->union($frontier);
        }

        $byParent = $services->groupBy(fn (Service $service): int => $services->has($service->parent_id)
            ? (int) $service->parent_id : 0);
        $visited = [];
        $build = function (int $parentId) use (&$build, &$visited, $byParent): array {
            $items = [];
            foreach ($byParent->get($parentId, collect())->sortBy('name') as $service) {
                $id = (int) $service->getKey();
                if (isset($visited[$id])) {
                    continue;
                }
                $visited[$id] = true;
                $items[] = $this->node($service, [
                    ...$this->emptyGroup($id),
                    'items' => $build($id),
                ]);
            }

            return $items;
        };

        return [
            'parent_id' => 0,
            'page' => $results->currentPage(),
            'has_more' => $results->hasMorePages(),
            'items' => $build(0),
        ];
    }

    private function branch(?int $parentId, array $expanded, array $pages, array $ancestors = []): array
    {
        $key = $parentId ?? 0;
        $query = $this->orderedQuery()
            ->select(self::COLUMNS)
            ->withExists(['children as public_children_exists' => fn (Builder $query) => $this->publicConstraints($query)]);

        if ($parentId === null) {
            // A public child remains accessible even if its parent is not public.
            $query->whereDoesntHave('parent', fn (Builder $query) => $this->publicConstraints($query));
        } else {
            $query->where('parent_id', $parentId);
        }

        $results = $query->simplePaginate(self::PER_PAGE, self::COLUMNS, 'serviceBranchPage', max(1, (int) ($pages[$key] ?? 1)));
        $items = [];

        foreach ($results as $service) {
            $id = (int) $service->getKey();
            if (isset($ancestors[$id])) {
                continue;
            }

            $children = isset($expanded[$id]) && $service->public_children_exists
                ? $this->branch($id, $expanded, $pages, $ancestors + [$id => true])
                : null;
            $items[] = $this->node($service, $children);
        }

        return [
            'parent_id' => $key,
            'page' => $results->currentPage(),
            'has_more' => $results->hasMorePages(),
            'items' => $items,
        ];
    }

    private function node(Service $service, ?array $children): array
    {
        return [
            'id' => (int) $service->getKey(),
            'title' => $service->name,
            'url' => $service->resolveNavigationUrl(),
            'has_children' => (bool) $service->public_children_exists,
            'children' => $children,
        ];
    }

    private function orderedQuery(): Builder
    {
        return $this->publicQuery()->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    private function publicQuery(): Builder
    {
        return $this->publicConstraints(Service::query());
    }

    private function publicConstraints(Builder $query): Builder
    {
        return $query->published()->whereNotNull('slug')->whereRaw("TRIM(slug) <> ''");
    }

    private function emptyGroup(int $parentId = 0): array
    {
        return ['parent_id' => $parentId, 'page' => 1, 'has_more' => false, 'items' => []];
    }
}
