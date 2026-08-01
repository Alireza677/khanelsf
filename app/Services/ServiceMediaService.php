<?php

namespace App\Services;

use App\Models\Service;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ServiceMediaService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function context(Service $service): array
    {
        $service->loadMissing('media');

        $featured = $service->getFirstMedia('featured_image');
        $featuredKey = $featured ? $this->uniqueKey($featured) : null;
        $gallery = $service->getMedia('gallery')
            ->unique(fn (Media $media): string => $this->uniqueKey($media))
            ->reject(fn (Media $media): bool => $featuredKey !== null
                && $this->uniqueKey($media) === $featuredKey)
            ->map(fn (Media $media): array => $this->mediaItem($media))
            ->values();
        $featuredItem = $featured ? $this->mediaItem($featured) : null;

        return [
            'featured' => $featuredItem,
            'gallery' => $gallery,
            'seo_image' => data_get($featuredItem, 'url')
                ?: data_get($gallery->first(), 'url')
                ?: $this->fallbackImage(),
        ];
    }

    public function seoImageUrl(Service $service, ?array $mediaContext = null): ?string
    {
        $mediaContext ??= $this->context($service);

        return filled($mediaContext['seo_image'] ?? null)
            ? (string) $mediaContext['seo_image']
            : null;
    }

    private function mediaItem(Media $media): array
    {
        return [
            'id' => $media->getKey(),
            'name' => $media->name,
            'fileName' => $media->file_name,
            'url' => $media->getUrl(),
            'mimeType' => $media->mime_type,
            'size' => (int) $media->size,
            'sortOrder' => (int) ($media->order_column ?? 0),
        ];
    }

    private function uniqueKey(Media $media): string
    {
        $sourceMediaId = $media->getCustomProperty('source_media_id');

        return filled($sourceMediaId)
            ? 'source:'.(string) $sourceMediaId
            : 'url:'.$media->getUrl();
    }

    private function fallbackImage(): ?string
    {
        return $this->settings->assetUrl($this->settings->get('default_og_image'))
            ?: $this->settings->imagePlaceholderUrl();
    }
}
