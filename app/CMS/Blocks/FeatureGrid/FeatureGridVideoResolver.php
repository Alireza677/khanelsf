<?php

namespace App\CMS\Blocks\FeatureGrid;

use App\Models\Video;
use App\Services\VideoLibrarySettings;
use App\Services\VideoQueryService;

final class FeatureGridVideoResolver
{
    public function __construct(
        private readonly VideoQueryService $videos,
        private readonly VideoLibrarySettings $settings,
    ) {}

    /** Resolve references once per grid, with eager-loaded shared media. */
    public function resolve(array $items, bool $preview): array
    {
        $ids = collect($items)->where('media_type', 'video')->pluck('video_id')->filter()->unique();
        $videos = $ids->isNotEmpty() && $this->settings->enabled()
            ? $this->videos->archiveQuery()->whereKey($ids->all())->get()->keyBy('id')
            : collect();

        $resolved = [];
        foreach ($items as $item) {
            if (($item['media_type'] ?? 'image') !== 'video') {
                $resolved[] = $item;

                continue;
            }
            $video = $videos->get($item['video_id']);
            $card = $video ? $this->card($video, $item) : null;
            if ($card !== null) {
                $resolved[] = $card;
            } elseif ($preview) {
                // Do not leak the hidden video's title, thumbnail, embed or action in preview.
                $resolved[] = array_merge($item, [
                    'title' => 'ویدئو در دسترس نیست', 'description' => null,
                    'image' => null, 'icon' => null, 'action' => null, 'button_label' => null,
                    'video_unavailable' => 'ویدئو حذف شده، منتشر نشده یا کتابخانه ویدئویی غیرفعال است.',
                ]);
            }
        }

        return $resolved;
    }

    /** Runtime-only presentation; never persisted into block JSON. */
    public function card(Video $video, array $item = []): ?array
    {
        if (! $video->exists || $video->status !== 'published' || $video->published_at?->isFuture()) {
            return null;
        }
        $embed = $video->embedUrl();
        if ($embed === null) {
            return null;
        }
        $custom = ($item['video_text_mode'] ?? 'dynamic') === 'custom';

        return array_merge($item, [
            'media_type' => 'video', 'video_id' => $video->getKey(),
            'title' => $custom ? ($item['title'] ?? null) : $video->title,
            'description' => $custom ? ($item['description'] ?? null) : $video->excerpt,
            'image' => $video->thumbnailUrl(), 'icon' => null,
            'video' => [
                'embed_url' => $embed,
                'title' => $video->title,
                'duration' => $video->formattedDuration(),
            ],
        ]);
    }

    public function dynamicCards(?int $categoryId, int $limit, string $sort): array
    {
        if (! $this->settings->enabled()) {
            return [];
        }

        return $this->videos->featureGridQuery($categoryId, $sort)->limit(max(1, min(72, $limit)))->get()
            ->map(fn (Video $video): ?array => $this->card($video))->filter()->values()->all();
    }
}
