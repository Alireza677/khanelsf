<?php

namespace App\Services;

use App\CMS\Collections\Service\ServiceArchiveGroup;
use App\CMS\Collections\Service\ServiceArchivePresentation;
use App\CMS\Collections\Service\ServiceCollectionAdapter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class ServiceArchiveContextBuilder
{
    public function __construct(private readonly ServiceCollectionAdapter $collections) {}

    public function build(
        LengthAwarePaginator $roots,
        Collection $children,
        string $heading,
        ?string $description,
    ): ServiceArchivePresentation {
        $childrenByRoot = $children->groupBy(fn ($service): int => (int) $service->parent_id);
        $pagination = $this->collections->paginationFor($roots);
        $lastRootId = $roots->getCollection()->last()?->getKey();

        $groups = $roots->getCollection()->map(function ($root) use ($childrenByRoot, $pagination, $lastRootId): ServiceArchiveGroup {
            $children = $childrenByRoot->get((int) $root->getKey(), collect())->values();
            $usesRootAsFallback = $children->isEmpty();
            $items = $usesRootAsFallback ? collect([$root]) : $children;

            return new ServiceArchiveGroup(
                rootId: (int) $root->getKey(),
                collection: $this->collections->adaptServices(
                    $items,
                    $root->name,
                    $root->excerpt,
                    $root->getKey() === $lastRootId ? $pagination : null,
                ),
                usesRootAsFallback: $usesRootAsFallback,
            );
        })->all();

        return new ServiceArchivePresentation(
            groups: $groups,
            emptyCollection: $this->collections->adaptServices(collect(), $heading, $description),
        );
    }
}
