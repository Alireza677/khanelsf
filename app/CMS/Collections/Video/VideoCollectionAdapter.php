<?php

namespace App\CMS\Collections\Video;

use App\CMS\Blocks\FeatureGrid\FeatureGridVideoResolver;
use App\CMS\Collections\Data\CollectionEmptyState;
use App\CMS\Collections\Data\CollectionImage;
use App\CMS\Collections\Data\CollectionItem;
use App\CMS\Collections\Data\CollectionMetaItem;
use App\CMS\Collections\Data\CollectionPagination;
use App\CMS\Collections\Data\CollectionPaginationLink;
use App\CMS\Collections\Data\CollectionPresentation;
use App\Models\Video;
use App\Services\VideoLibrarySettings;
use Illuminate\Pagination\LengthAwarePaginator;

final class VideoCollectionAdapter
{
    public function __construct(
        private readonly FeatureGridVideoResolver $videos,
        private readonly VideoLibrarySettings $settings,
    ) {}

    public function adapt(LengthAwarePaginator $videos, string $title, ?string $description = null): VideoArchivePresentation
    {
        $cards = $this->settings->enabled()
            ? $videos->getCollection()->map(fn (Video $video): ?array => $this->videos->card($video))->filter()->values()
            : collect();
        $collection = new CollectionPresentation(
            title: $title,
            description: $description,
            items: $cards->map(fn (array $card): CollectionItem => new CollectionItem(
                title: $card['title'],
                image: new CollectionImage($card['image'], $card['title']),
                excerpt: $card['description'],
                metaItems: $card['video']['duration'] !== null ? [new CollectionMetaItem('مدت', $card['video']['duration'])] : [],
            ))->all(),
            emptyState: new CollectionEmptyState('هنوز ویدئویی منتشر نشده است.'),
            pagination: $videos->hasPages() ? new CollectionPagination(
                currentPage: $videos->currentPage(),
                lastPage: $videos->lastPage(),
                previousUrl: $videos->previousPageUrl(),
                nextUrl: $videos->nextPageUrl(),
                links: collect($videos->linkCollection())->map(fn (array $link): CollectionPaginationLink => new CollectionPaginationLink(
                    label: html_entity_decode(strip_tags((string) $link['label']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    url: $link['url'],
                    active: (bool) $link['active'],
                ))->all(),
                ariaLabel: 'صفحه‌بندی ویدئوها',
            ) : null,
        );

        return new VideoArchivePresentation($collection, [
            'settings' => ['variant' => 'default'], 'items' => $cards->all(), 'dynamic' => false, 'grid_style' => '',
        ]);
    }
}
