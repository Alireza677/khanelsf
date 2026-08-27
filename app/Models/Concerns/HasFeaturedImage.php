<?php

namespace App\Models\Concerns;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasFeaturedImage
{
    use HasSharedMediaUsages;

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
        return $this->firstSharedMedia('featured_image')
            ?: $this->getFirstMedia('featured_image');
    }

    public function featuredImageUrl(?string $conversionName = null): ?string
    {
        $media = $this->featuredImage();

        if (! $media) {
            return null;
        }

        return filled($conversionName) && $media->hasGeneratedConversion($conversionName)
            ? $media->getUrl($conversionName)
            : $media->getUrl();
    }
}
