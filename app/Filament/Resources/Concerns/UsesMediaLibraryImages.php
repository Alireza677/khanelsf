<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
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
            $record->clearMediaCollection('featured_image');

            return;
        }

        $media = Media::query()
            ->where('collection_name', 'media_library')
            ->where('mime_type', 'like', 'image/%')
            ->findOrFail($mediaId);

        if (! file_exists($media->getPath())) {
            return;
        }

        $record
            ->addMedia($media->getPath())
            ->preservingOriginal()
            ->usingName($media->name)
            ->usingFileName($media->file_name)
            ->withCustomProperties(['source_media_id' => $media->id])
            ->toMediaCollection('featured_image', 'public');
    }

    public static function mediaLibraryCollectionState(Model $record, string $collection): array
    {
        return $record->getMedia($collection)
            ->map(fn (Media $media): string => filled($media->getCustomProperty('source_media_id'))
                ? (string) $media->getCustomProperty('source_media_id')
                : "existing:{$media->id}")
            ->values()
            ->all();
    }

    public static function mediaLibraryImageItemsWithCollection(Model $record, string $collection): array
    {
        $libraryItems = collect(static::mediaLibraryImageItems());
        $legacyItems = $record->getMedia($collection)
            ->reject(fn (Media $media): bool => filled($media->getCustomProperty('source_media_id')))
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

        $record->getMedia($collection)->each(function (Media $media) use ($existingIds, $sourceIds): void {
            $sourceId = $media->getCustomProperty('source_media_id');
            $shouldKeep = filled($sourceId)
                ? in_array((int) $sourceId, $sourceIds, true)
                : in_array((int) $media->id, $existingIds, true);

            if (! $shouldKeep) {
                $media->delete();
            }
        });

        $attachedSourceIds = $record->fresh()->getMedia($collection)
            ->pluck('custom_properties')
            ->map(fn (array $properties): int => (int) ($properties['source_media_id'] ?? 0))
            ->filter()
            ->all();

        Media::query()
            ->whereIn('id', array_diff($sourceIds, $attachedSourceIds))
            ->where('collection_name', 'media_library')
            ->where('mime_type', 'like', 'image/%')
            ->get()
            ->each(function (Media $media) use ($record, $collection): void {
                if (! file_exists($media->getPath())) {
                    return;
                }

                $record
                    ->addMedia($media->getPath())
                    ->preservingOriginal()
                    ->usingName($media->name)
                    ->usingFileName($media->file_name)
                    ->withCustomProperties(['source_media_id' => $media->id])
                    ->toMediaCollection($collection, 'public');
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
