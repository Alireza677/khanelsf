<?php

namespace App\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver as SpatieObserver;
use Throwable;

final class MediaObserver extends SpatieObserver
{
    public function deleted(Media $media): void
    {
        if (method_exists($media, 'isForceDeleting') && ! $media->isForceDeleting()) {
            return;
        }
        $media = clone $media;
        $cleanup = function () use ($media): void {
            try {
                parent::deleted($media);
                if (Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())) {
                    throw new \RuntimeException('Physical media cleanup failed for media '.$media->getKey());
                }
            } catch (Throwable $exception) {
                app(MediaDependencyService::class)->cleanupFailures[] = $media->getKey();
                report($exception);
            }
        };
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($cleanup);
        } else {
            $cleanup();
        }
    }
}
