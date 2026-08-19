<?php

namespace App\CMS\Collections\Service;

use App\CMS\Collections\Data\CollectionAction;
use App\CMS\Collections\Data\CollectionEmptyState;
use App\CMS\Collections\Data\CollectionImage;
use App\CMS\Collections\Data\CollectionItem;
use App\CMS\Collections\Data\CollectionPagination;
use App\CMS\Collections\Data\CollectionPaginationLink;
use App\CMS\Collections\Data\CollectionPresentation;
use App\Models\Service;
use Illuminate\Pagination\LengthAwarePaginator;

final class ServiceCollectionAdapter
{
    public function adapt(LengthAwarePaginator $services, string $title, ?string $description = null): CollectionPresentation
    {
        return new CollectionPresentation(
            title: $title,
            description: $this->text($description),
            items: $services->getCollection()->map(fn (Service $service): CollectionItem => $this->item($service))->all(),
            pagination: $services->hasPages() ? $this->pagination($services) : null,
            emptyState: new CollectionEmptyState('هنوز خدمتی منتشر نشده است.'),
            variant: 'clean_grid',
            columns: 3,
        );
    }

    public function item(Service $service): CollectionItem
    {
        $imageUrl = $this->text($service->getFirstMediaUrl('featured_image', 'thumb'));
        $url = $this->text($service->resolveNavigationUrl());

        return new CollectionItem(
            title: $service->name,
            image: $imageUrl ? new CollectionImage($imageUrl, $service->name) : null,
            icon: $this->text($service->icon),
            excerpt: $this->text($service->excerpt),
            action: $url ? new CollectionAction('مشاهده خدمت', $url) : null,
        );
    }

    private function pagination(LengthAwarePaginator $paginator): CollectionPagination
    {
        $links = collect($paginator->linkCollection())->map(fn (array $link): CollectionPaginationLink => new CollectionPaginationLink(
            label: html_entity_decode(strip_tags((string) $link['label']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            url: $link['url'],
            active: (bool) $link['active'],
        ))->all();

        return new CollectionPagination(
            currentPage: $paginator->currentPage(),
            lastPage: $paginator->lastPage(),
            previousUrl: $paginator->previousPageUrl(),
            nextUrl: $paginator->nextPageUrl(),
            links: $links,
            ariaLabel: 'صفحه‌بندی خدمات',
        );
    }

    private function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
