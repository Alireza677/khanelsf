<?php

namespace App\CMS\Collections\Blog;

use App\CMS\Collections\Data\CollectionAction;
use App\CMS\Collections\Data\CollectionEmptyState;
use App\CMS\Collections\Data\CollectionImage;
use App\CMS\Collections\Data\CollectionItem;
use App\CMS\Collections\Data\CollectionMetaItem;
use App\CMS\Collections\Data\CollectionPagination;
use App\CMS\Collections\Data\CollectionPaginationLink;
use App\CMS\Collections\Data\CollectionPresentation;
use App\Models\Post;
use App\Support\PersianDate;
use Illuminate\Pagination\LengthAwarePaginator;

final class BlogCollectionAdapter
{
    public function adapt(
        LengthAwarePaginator $posts,
        string $title = 'وبلاگ',
        ?string $description = null,
        string $emptyMessage = 'هنوز نوشته‌ای منتشر نشده است.',
    ): CollectionPresentation {
        return new CollectionPresentation(
            title: $title,
            description: $this->text($description),
            items: $posts->getCollection()->map(fn (Post $post): CollectionItem => $this->item($post))->all(),
            pagination: $posts->hasPages() ? $this->pagination($posts) : null,
            emptyState: new CollectionEmptyState($emptyMessage),
            variant: 'clean_grid',
            columns: 3,
        );
    }

    public function item(Post $post): CollectionItem
    {
        $imageUrl = $this->text($post->featuredImageUrl('thumb'));
        $url = $this->text($post->resolveNavigationUrl());
        $category = $this->text($post->category?->title);

        return new CollectionItem(
            title: $post->title,
            image: $imageUrl ? new CollectionImage($imageUrl, $post->title) : null,
            excerpt: $this->text($post->excerpt),
            action: $url ? new CollectionAction('مشاهده نوشته', $url) : null,
            metaItems: $post->published_at
                ? [new CollectionMetaItem('تاریخ انتشار', PersianDate::dateWithWeekday($post->published_at))]
                : [],
            badges: $category ? [$category] : [],
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
            ariaLabel: 'صفحه‌بندی نوشته‌ها',
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
