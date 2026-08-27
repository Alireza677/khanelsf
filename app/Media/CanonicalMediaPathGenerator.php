<?php

namespace App\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CanonicalMediaPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->basePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->basePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->basePath($media).'/responsive-images/';
    }

    private function basePath(Media $media): string
    {
        if (filled($media->asset_key)) {
            return 'media/'.$media->asset_key;
        }

        $prefix = trim((string) config('media-library.prefix', ''), '/');

        return $prefix !== '' ? $prefix.'/'.$media->getKey() : (string) $media->getKey();
    }
}
