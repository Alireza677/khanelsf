<?php

namespace App\Models\Concerns;

use App\Models\MediaUsage;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait HasSharedMediaUsages
{
    protected static function bootHasSharedMediaUsages(): void
    {
        static::deleting(function ($model): void {
            $model->mediaUsages()->delete();
        });
    }

    public function mediaUsages(): MorphMany
    {
        return $this->morphMany(MediaUsage::class, 'usable');
    }

    /** @return Collection<int, Media> */
    public function sharedMedia(string $collection): Collection
    {
        $usages = $this->relationLoaded('mediaUsages')
            ? $this->mediaUsages
            : $this->mediaUsages()->with('media')->get();

        return $usages
            ->where('collection_name', $collection)
            ->sortBy([['order_column', 'asc'], ['id', 'asc']])
            ->pluck('media')
            ->filter()
            ->values();
    }

    public function firstSharedMedia(string $collection): ?Media
    {
        return $this->sharedMedia($collection)->first();
    }
}
