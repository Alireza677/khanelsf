<?php

namespace App\CMS\Collections\Project;

use App\CMS\Collections\Data\CollectionAction;
use App\CMS\Collections\Data\CollectionEmptyState;
use App\CMS\Collections\Data\CollectionImage;
use App\CMS\Collections\Data\CollectionItem;
use App\CMS\Collections\Data\CollectionMetaItem;
use App\CMS\Collections\Data\CollectionPagination;
use App\CMS\Collections\Data\CollectionPaginationLink;
use App\CMS\Collections\Data\CollectionPresentation;
use App\Models\Project;
use App\Support\PersianDate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class ProjectCollectionAdapter
{
    public function adapt(
        LengthAwarePaginator $projects,
        string $title,
        ?string $description = null,
        string $emptyMessage = 'هنوز پروژه‌ای منتشر نشده است.',
    ): CollectionPresentation {
        return new CollectionPresentation(
            title: $title,
            description: $this->text($description),
            items: $projects->getCollection()->map(fn (Project $project): CollectionItem => $this->item($project))->all(),
            pagination: $projects->hasPages() ? $this->pagination($projects) : null,
            emptyState: new CollectionEmptyState($emptyMessage),
            variant: 'masonry_gallery',
            columns: 3,
        );
    }

    public function item(Project $project): CollectionItem
    {
        $imageUrl = $this->text($project->coverImageUrl(null));
        $url = $this->text($project->resolveNavigationUrl());
        $category = $this->text($project->category?->name);

        return new CollectionItem(
            title: $project->title,
            image: $imageUrl ? new CollectionImage($imageUrl, $project->title) : null,
            excerpt: $this->excerpt($project),
            action: $url ? new CollectionAction('مشاهده پروژه', $url) : null,
            metaItems: $this->metaItems($project),
            badges: $category ? [$category] : [],
        );
    }

    private function excerpt(Project $project): ?string
    {
        foreach ([$project->excerpt, $project->content] as $source) {
            $plainText = preg_replace('/<\s*(?:br\b[^>]*|\/(?:p|div|li|h[1-6]|section|article|blockquote))\s*>/i', ' ', (string) $source);
            $plainText = Str::squish(html_entity_decode(strip_tags($plainText), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ($plainText !== '') {
                return Str::words($plainText, 35, '…');
            }
        }

        return null;
    }

    /** @return array<CollectionMetaItem> */
    private function metaItems(Project $project): array
    {
        return collect([
            ['موقعیت', $this->text($project->location)],
            ['نوع پروژه', $this->text($project->project_type)],
            ['تاریخ', PersianDate::date($project->project_date)],
        ])->filter(fn (array $item): bool => filled($item[1]))
            ->map(fn (array $item): CollectionMetaItem => new CollectionMetaItem($item[0], $item[1]))
            ->values()
            ->all();
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
            ariaLabel: 'صفحه‌بندی پروژه‌ها',
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
