<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media as SpatieMedia;

class Media extends SpatieMedia
{
    protected static function booted(): void
    {
        static::updating(function (self $media): void {
            if ($media->isDirty('asset_key')) {
                throw new LogicException('The canonical media asset key is immutable.');
            }
        });
    }

    public function displayTitle(): string
    {
        return filled($this->name)
            ? (string) $this->name
            : pathinfo($this->file_name, PATHINFO_FILENAME);
    }

    public function altText(): string
    {
        return (string) $this->getCustomProperty('alt_text', '');
    }

    public function originalFilename(): string
    {
        return filled($this->original_filename)
            ? (string) $this->original_filename
            : (string) $this->file_name;
    }

    public function scopeReusableImages(Builder $query): Builder
    {
        return $query
            ->where('collection_name', 'media_library')
            ->where('mime_type', 'like', 'image/%');
    }

    public function isReusableImage(): bool
    {
        return $this->collection_name === 'media_library'
            && str_starts_with((string) $this->mime_type, 'image/')
            && Storage::disk($this->disk)->exists($this->getPathRelativeToRoot());
    }

    public static function reusableImage(int|string|null $mediaId): ?self
    {
        if (! is_numeric($mediaId) || (int) $mediaId < 1) {
            return null;
        }

        $media = static::query()->reusableImages()->find((int) $mediaId);

        return $media?->isReusableImage() ? $media : null;
    }

    /** @return array{width: int, height: int}|null */
    public function imageDimensions(): ?array
    {
        if (! str_starts_with((string) $this->mime_type, 'image/') || ! is_file($this->getPath())) {
            return null;
        }

        $dimensions = @getimagesize($this->getPath());

        if (! is_array($dimensions)) {
            return null;
        }

        return ['width' => $dimensions[0], 'height' => $dimensions[1]];
    }
}
