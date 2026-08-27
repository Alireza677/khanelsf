<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait UsesMediaLibraryImages
{
    protected static ?array $mediaLibraryImageItemsCache = null;

    protected static ?array $mediaLibraryVideoItemsCache = null;

    public static function mediaLibraryImageItems(): array
    {
        if (static::$mediaLibraryImageItemsCache !== null) {
            return static::$mediaLibraryImageItemsCache;
        }

        $startedAt = hrtime(true);

        static::$mediaLibraryImageItemsCache = Media::query()
            ->where('collection_name', 'media_library')
            ->where('mime_type', 'like', 'image/%')
            ->latest()
            ->get()
            ->filter(fn (Media $media): bool => file_exists($media->getPath()))
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->file_name,
                'url' => $media->getUrl(),
            ])
            ->values()
            ->all();

        static::logMediaLibraryPerf('media image fields load ms', $startedAt, count(static::$mediaLibraryImageItemsCache));

        return static::$mediaLibraryImageItemsCache;
    }

    public static function mediaLibraryVideoItems(): array
    {
        if (static::$mediaLibraryVideoItemsCache !== null) {
            return static::$mediaLibraryVideoItemsCache;
        }

        $startedAt = hrtime(true);

        static::$mediaLibraryVideoItemsCache = Media::query()
            ->where('collection_name', 'media_library')
            ->where('mime_type', 'like', 'video/%')
            ->latest()
            ->get()
            ->filter(fn (Media $media): bool => file_exists($media->getPath()))
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'name' => $media->file_name,
                'url' => $media->getUrl(),
            ])
            ->values()
            ->all();

        static::logMediaLibraryPerf('media video fields load ms', $startedAt, count(static::$mediaLibraryVideoItemsCache));

        return static::$mediaLibraryVideoItemsCache;
    }

    public static function syncFeaturedImage(Model $record, int|string|null $mediaId): void
    {
        if ($mediaId === '__keep_existing__') {
            return;
        }

        if (blank($mediaId)) {
            $record->mediaUsages()->where('collection_name', 'featured_image')->delete();
            $record->clearMediaCollection('featured_image');

            return;
        }

        $media = Media::query()
            ->where('collection_name', 'media_library')
            ->where('mime_type', 'like', 'image/%')
            ->findOrFail($mediaId);

        DB::transaction(function () use ($record, $media): void {
            $record->mediaUsages()->where('collection_name', 'featured_image')->delete();
            $record->mediaUsages()->create([
                'media_id' => $media->id,
                'collection_name' => 'featured_image',
                'order_column' => 0,
            ]);
            $record->clearMediaCollection('featured_image');
        });
    }

    public static function mediaLibraryFeaturedState(?Model $record): int|string|null
    {
        if (! $record) {
            return null;
        }

        $sharedId = $record->mediaUsages()
            ->where('collection_name', 'featured_image')
            ->value('media_id');

        if ($sharedId) {
            return (int) $sharedId;
        }

        $legacy = $record->getFirstMedia('featured_image');

        return $legacy?->getCustomProperty('source_media_id')
            ?: ($legacy ? '__keep_existing__' : null);
    }

    public static function mediaLibraryCollectionState(Model $record, string $collection): array
    {
        $sharedIds = $record->mediaUsages()
            ->where('collection_name', $collection)
            ->orderBy('order_column')
            ->orderBy('id')
            ->pluck('media_id')
            ->map(fn ($id): string => (string) $id);

        if ($sharedIds->isNotEmpty()) {
            return $sharedIds->all();
        }

        return $record->getMedia($collection)
            ->map(fn (Media $media): string => "existing:{$media->id}")
            ->values()
            ->all();
    }

    public static function mediaLibraryImageItemsWithCollection(Model $record, string $collection): array
    {
        $libraryItems = collect(static::mediaLibraryImageItems());
        $legacyItems = $record->getMedia($collection)
            ->map(fn (Media $media): array => [
                'id' => "existing:{$media->id}",
                'name' => $media->file_name,
                'url' => $media->getUrl(),
            ]);

        return $legacyItems->concat($libraryItems)->values()->all();
    }

    public static function syncMediaLibraryCollection(Model $record, string $collection, ?array $selection): void
    {
        $selection = collect($selection ?? [])->map(fn ($id): string => (string) $id)->unique()->values();
        $existingIds = $selection
            ->filter(fn (string $id): bool => str_starts_with($id, 'existing:'))
            ->map(fn (string $id): int => (int) str($id)->after('existing:')->toString())
            ->all();
        $sourceIds = $selection
            ->reject(fn (string $id): bool => str_starts_with($id, 'existing:'))
            ->filter(fn (string $id): bool => ctype_digit($id))
            ->map(fn (string $id): int => (int) $id)
            ->all();

        $validSourceIds = Media::query()
            ->whereIn('id', $sourceIds)
            ->where('collection_name', 'media_library')
            ->where('mime_type', 'like', 'image/%')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        DB::transaction(function () use ($record, $collection, $selection, $existingIds, $validSourceIds): void {
            $record->mediaUsages()->where('collection_name', $collection)->delete();

            foreach ($selection as $position => $selectedId) {
                $mediaId = (int) $selectedId;

                if (in_array($mediaId, $validSourceIds, true)) {
                    $record->mediaUsages()->create([
                        'media_id' => $mediaId,
                        'collection_name' => $collection,
                        'order_column' => $position,
                    ]);
                }
            }

            $record->getMedia($collection)->each(function (Media $media) use ($existingIds): void {
                if (! in_array((int) $media->id, $existingIds, true)) {
                    $media->delete();
                }
            });
        });
    }

    protected static function logMediaLibraryPerf(string $label, int $startedAt, int $count): void
    {
        if (! request()->routeIs('filament.admin.resources.pages.edit')) {
            return;
        }

        Log::info("PERF PageResource edit: {$label}", [
            'ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            'items' => $count,
        ]);
    }
}
