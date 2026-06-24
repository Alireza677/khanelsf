<?php

namespace App\Models\Concerns;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasFeaturedImage
{
    public function registerMediaCollections(): void
    {
        $this->registerFeaturedImageMediaCollection();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerFeaturedImageMediaConversions($media);
    }

    protected function registerFeaturedImageMediaCollection(): void
    {
        $this
            ->addMediaCollection('featured_image')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    protected function registerFeaturedImageMediaConversions(?Media $media = null): void
    {
        $this
            ->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->nonQueued();
    }

    public function featuredImage(): ?Media
    {
        return $this->getFirstMedia('featured_image');
    }

    public function featuredImageUrl(?string $conversionName = null): ?string
    {
        $url = $this->getFirstMediaUrl('featured_image', $conversionName ?? '');

        return $url ?: null;
    }
}
