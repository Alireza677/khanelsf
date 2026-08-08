<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\ProjectVideo;
use App\Models\Redirect;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Filesystem as MediaFilesystem;
use Throwable;

final class LegacyGalleryMigrationService
{
    public function __construct(
        private readonly LegacyGalleryMigrationAuditService $audit,
        private readonly ExternalVideoResolver $videos,
        private readonly MediaFilesystem $mediaFilesystem,
    ) {}

    /** @return array<string, mixed> */
    public function plan(int $galleryId): array
    {
        $report = $this->audit->audit($galleryId);
        $analysis = $report['galleries'][0] ?? null;
        $gallery = Gallery::query()->with(['project.media', 'project.videos', 'media'])->find($galleryId);
        $blockers = [];

        if (! $gallery) {
            return $this->blockedPlan($galleryId, 'gallery_not_found');
        }
        if (! $gallery->project_id) {
            $blockers[] = 'gallery_has_no_project_id';
        }
        if (! $gallery->project) {
            $blockers[] = 'target_project_missing';
        }
        foreach (['project_videos', 'project_discovery_vocabularies', 'project_discovery_terms', 'project_discovery_term_project'] as $table) {
            if (! Schema::hasTable($table)) {
                $blockers[] = 'required_schema_missing:'.$table;
            }
        }
        if ($analysis && ($analysis['redirect']['conflict'] || $analysis['redirect']['loop'])) {
            $blockers[] = $analysis['redirect']['conflict'] ? 'redirect_conflict' : 'redirect_loop';
        }
        if ($analysis && ! $analysis['redirect']['target_is_published']) {
            $blockers[] = 'target_project_not_published';
        }
        if ($analysis && $analysis['video']['has_video'] && ! $analysis['video']['safe_url']) {
            $blockers[] = 'unsafe_video_url';
        }

        $project = $gallery->project;
        $sourceMedia = collect([$gallery->featuredImage()])->filter()->merge($gallery->images())->unique('id')->values();
        $alreadyMedia = $project ? $project->media->filter(fn ($media): bool => (int) $media->getCustomProperty('legacy_gallery_id') === $galleryId)->values() : collect();
        $copyMedia = $sourceMedia->reject(fn ($media): bool => $alreadyMedia->contains(fn ($target): bool => (int) $target->getCustomProperty('legacy_gallery_media_id') === (int) $media->getKey()))->values();
        $normalizedVideo = filled($gallery->video_url) ? $this->videos->resolve($gallery->video_url)['external_url'] : null;
        $matchingVideo = $project && $normalizedVideo ? $project->videos->first(fn (ProjectVideo $video): bool => $this->videos->resolve($video->url)['external_url'] === $normalizedVideo) : null;
        $redirect = $analysis['redirect'] ?? null;
        $exactRedirect = $redirect && $redirect['existing_redirect_id'] && ! $redirect['conflict']
            && $redirect['existing_is_active'] && $redirect['existing_status_code'] === 301;

        return [
            'gallery_id' => $galleryId,
            'gallery' => $gallery->title,
            'target' => $project?->title,
            'source_url' => $redirect['source'] ?? null,
            'target_url' => $redirect['proposed_target'] ?? null,
            'blockers' => array_values(array_unique($blockers)),
            'warnings' => array_values(array_diff($analysis['warnings'] ?? [], ['taxonomy_mapping_required'])),
            'actions' => [
                'copy_media' => $copyMedia->count(),
                'already_migrated_media' => $alreadyMedia->count(),
                'create_project_video' => filled($gallery->video_url) && ! $matchingVideo ? 1 : 0,
                'already_migrated_videos' => $matchingVideo ? 1 : 0,
                'copy_content' => 0,
                'copy_seo' => 0,
                'taxonomy' => 'NO_ACTION',
                'create_redirect' => $exactRedirect ? 'NO_ACTION' : 'YES',
            ],
            '_source_media_ids' => $copyMedia->pluck('id')->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function apply(int $galleryId): array
    {
        $plan = $this->plan($galleryId);
        if ($plan['blockers'] !== []) {
            throw new RuntimeException('Migration blocked: '.implode(', ', $plan['blockers']));
        }

        $result = ['copied_media' => 0, 'skipped_media' => $plan['actions']['already_migrated_media'], 'created_videos' => 0, 'skipped_videos' => $plan['actions']['already_migrated_videos'], 'redirect' => 'existing'];

        return DB::transaction(function () use ($galleryId, $plan, $result): array {
            $gallery = Gallery::query()->with(['project.media', 'project.videos', 'media'])->lockForUpdate()->findOrFail($galleryId);
            $project = $gallery->project;
            $createdMedia = collect();

            try {
                $nextOrder = ((int) $project->getMedia('gallery')->max('order_column')) + 1;
                foreach ($gallery->media->whereIn('id', $plan['_source_media_ids']) as $source) {
                    $copy = $project->media()->create([
                        'uuid' => (string) str()->uuid(),
                        'collection_name' => 'gallery',
                        'name' => $source->name,
                        'file_name' => $source->file_name,
                        'mime_type' => $source->mime_type,
                        'disk' => 'public',
                        'conversions_disk' => 'public',
                        'size' => $source->size,
                        'manipulations' => [],
                        'custom_properties' => [
                            'legacy_gallery_id' => $gallery->getKey(),
                            'legacy_gallery_media_id' => $source->getKey(),
                        ],
                        'generated_conversions' => [],
                        'responsive_images' => [],
                        'order_column' => $nextOrder++,
                    ]);
                    $createdMedia->push($copy);
                    $this->mediaFilesystem->copyToMediaLibrary($source->getPath(), $copy);
                    $result['copied_media']++;
                }

                if ($plan['actions']['create_project_video'] === 1) {
                    $project->videos()->create([
                        'url' => $gallery->video_url,
                        'title' => $gallery->title,
                        'caption' => $gallery->excerpt,
                        'thumbnail_url' => $gallery->thumbnail_url,
                        'sort_order' => ((int) $project->videos()->max('sort_order')) + 1,
                    ]);
                    $result['created_videos']++;
                }

                $source = Redirect::normalizePath($plan['source_url']);
                $existing = Redirect::query()->where('source_path', $source)->first();
                if (! $existing) {
                    Redirect::query()->create(['source_path' => $source, 'target_url' => $plan['target_url'], 'status_code' => 301, 'is_active' => true, 'note' => 'Phase 5B legacy Gallery migration']);
                    $result['redirect'] = 'created';
                } elseif (! $existing->is_active || $existing->status_code !== 301) {
                    $existing->update(['status_code' => 301, 'is_active' => true]);
                    $result['redirect'] = 'activated';
                }
            } catch (Throwable $exception) {
                $createdMedia->reverse()->each(fn ($media) => $media->delete());
                throw $exception;
            }

            return $result;
        });
    }

    private function blockedPlan(int $galleryId, string $blocker): array
    {
        return ['gallery_id' => $galleryId, 'gallery' => null, 'target' => null, 'source_url' => null, 'target_url' => null, 'blockers' => [$blocker], 'warnings' => [], 'actions' => ['copy_media' => 0, 'already_migrated_media' => 0, 'create_project_video' => 0, 'already_migrated_videos' => 0, 'copy_content' => 0, 'copy_seo' => 0, 'taxonomy' => 'NO_ACTION', 'create_redirect' => 'NO'], '_source_media_ids' => []];
    }
}
