<?php

namespace App\Models;

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
