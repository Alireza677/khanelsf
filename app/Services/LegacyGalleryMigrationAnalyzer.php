<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Project;
use App\Models\Redirect;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class LegacyGalleryMigrationAnalyzer
{
    public function __construct(private readonly ExternalVideoResolver $videos) {}

    /**
     * @param Collection<int, Project> $projects
     * @param Collection<string, Redirect> $redirects
     * @param array<string, array<int, array<string, mixed>>> $references
     * @return array<string, mixed>
     */
    public function analyze(
        Gallery $gallery,
        Collection $projects,
        Collection $redirects,
        array $references = [],
        bool $discoverySchemaAvailable = true,
    ): array {
        $project = $gallery->project_id ? $projects->firstWhere('id', $gallery->project_id) : null;
        $brokenReference = $gallery->project_id !== null && ! $project;
        $candidates = $gallery->project_id === null ? $this->candidates($gallery, $projects) : collect();
        $mediaClass = in_array($gallery->type, ['video', 'mixed'], true) || filled($gallery->video_url) ? 'D' : null;
        $video = $this->videoAnalysis($gallery, $project);
        $differences = $project ? $this->differences($gallery, $project) : null;
        $taxonomy = $this->taxonomyAnalysis($gallery, $project, $discoverySchemaAvailable);
        $redirect = $this->redirectAnalysis($gallery, $project, $redirects);
        $targetRepresentationComplete = $project !== null
            && ($differences['media']['gallery_only_images'] ?? []) === []
            && ! ($differences['media']['gallery_video_requires_migration'] ?? false);
        $migrationCompleted = $targetRepresentationComplete && $redirect['completed'];
        $warnings = [];
        $blockers = [];

        if ($brokenReference) {
            $blockers[] = 'broken_project_reference';
        }
        if ($video['has_video'] && ! $video['safe_url']) {
            $blockers[] = 'unsafe_video_url';
        }
        if ($redirect['conflict']) {
            $blockers[] = 'redirect_conflict';
        }
        if ($gallery->project_id === null) {
            $warnings[] = 'human_business_classification_required';
        }
        if ($taxonomy['mapping_required']) {
            $warnings[] = 'taxonomy_mapping_required';
        }
        if ($project && $differences['has_conflicts'] && ! $migrationCompleted) {
            $warnings[] = 'content_or_media_review_required';
        }
        if ($project && ! $this->isPubliclyPublished($project)) {
            $warnings[] = 'target_project_not_publicly_published';
        }

        $businessClass = $project ? 'A' : null;
        $suggestedClass = $businessClass ?: ($candidates->isNotEmpty() ? 'B' : 'C');
        $suggestionReasons = $businessClass
            ? ['valid_project_reference']
            : ($candidates->isNotEmpty()
                ? ['one_or_more_existing_project_candidates']
                : ['no_safe_existing_project_candidate_found', 'human_review_required']);

        $readiness = match (true) {
            $blockers !== [] => 'BLOCKED',
            $migrationCompleted => 'READY',
            ! $project => 'REVIEW_REQUIRED',
            $warnings !== [] => 'REVIEW_REQUIRED',
            default => 'READY',
        };

        return [
            'gallery_id' => $gallery->getKey(),
            'title' => $gallery->title,
            'slug' => $gallery->slug,
            'current_url' => route('galleries.show', $gallery->slug, absolute: false),
            'status' => $gallery->status,
            'published_at' => $gallery->published_at?->toAtomString(),
            'type' => $gallery->type,
            'project_id' => $gallery->project_id,
            'project' => $project ? $this->projectSummary($project) : null,
            'broken_project_reference' => $brokenReference,
            'category' => $gallery->category ? [
                'id' => $gallery->category->getKey(),
                'name' => $gallery->category->name,
                'slug' => $gallery->category->slug,
            ] : null,
            'featured_image_present' => $gallery->featuredImage() !== null,
            'image_count' => $gallery->images()->count(),
            'video' => $video,
            'seo' => [
                'gallery_canonical' => route('galleries.show', $gallery->slug, absolute: false),
                'project_canonical' => $project ? route('projects.show', $project->slug, absolute: false) : null,
                'title_present' => filled($gallery->seo_title),
                'description_present' => filled($gallery->seo_description),
                'image_present' => filled($gallery->seo_image),
                'robots_index' => (bool) $gallery->robots_index,
                'robots_follow' => (bool) $gallery->robots_follow,
            ],
            'content' => [
                'excerpt_present' => filled($gallery->excerpt),
                'body_present' => filled($gallery->content),
                'body_length' => mb_strlen(trim(strip_tags((string) $gallery->content))),
            ],
            'business_class' => $businessClass,
            'suggested_class' => $suggestedClass,
            'suggestion_reasons' => $suggestionReasons,
            'media_class' => $mediaClass,
            'candidates' => $candidates->values()->all(),
            'differences' => $differences,
            'taxonomy' => $taxonomy,
            'redirect' => $redirect,
            'migration_evidence' => [
                'target_representation_complete' => $targetRepresentationComplete,
                'active_permanent_redirect' => $redirect['completed'],
                'migration_completed' => $migrationCompleted,
            ],
            'stored_references' => $references[$gallery->slug] ?? [],
            'warnings' => array_values(array_unique($warnings)),
            'blockers' => array_values(array_unique($blockers)),
            'human_review_required' => $readiness !== 'READY',
            'recommended_action' => $migrationCompleted ? 'NO_ACTION_MIGRATED' : ($project ? 'REVIEW_AND_MIGRATE_TO_LINKED_PROJECT' : ($candidates->isNotEmpty() ? 'LINK_EXISTING_PROJECT' : 'REVIEW_CREATE_PROJECT_OR_EDITORIAL_DESTINATION')),
            'readiness' => $readiness,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function candidates(Gallery $gallery, Collection $projects): Collection
    {
        $galleryTitle = $this->normalize($gallery->title);
        $gallerySlug = $this->normalize($gallery->slug);
        $galleryTokens = $this->tokens($gallery->title);

        return $projects->map(function (Project $project) use ($galleryTitle, $gallerySlug, $galleryTokens): ?array {
            $reasons = [];
            $score = 0;

            if ($galleryTitle !== '' && $galleryTitle === $this->normalize($project->title)) {
                $score += 100;
                $reasons[] = 'exact_normalized_title';
            }
            if ($gallerySlug !== '' && $gallerySlug === $this->normalize($project->slug)) {
                $score += 100;
                $reasons[] = 'exact_normalized_slug';
            }

            $projectTokens = $this->tokens($project->title);
            $overlap = $galleryTokens->intersect($projectTokens)->count();
            $denominator = max(1, min($galleryTokens->count(), $projectTokens->count()));
            if ($overlap > 0 && ($overlap / $denominator) >= 0.6) {
                $score += (int) round(($overlap / $denominator) * 50);
                $reasons[] = 'strong_title_token_overlap';
            }

            return $score > 0 ? [
                ...$this->projectSummary($project),
                'score' => $score,
                'match_reasons' => $reasons,
            ] : null;
        })->filter()->sortByDesc('score')->take(5)->values();
    }

    /** @return array<string, mixed> */
    private function differences(Gallery $gallery, Project $project): array
    {
        $fields = [
            'title' => $this->compare($gallery->title, $project->title),
            'slug' => $this->compare($gallery->slug, $project->slug),
            'excerpt' => $this->compare($gallery->excerpt, $project->excerpt),
            'body_content' => $this->compare($gallery->content, $project->content),
            'seo_title' => $this->compare($gallery->seo_title, $project->seo_title),
            'seo_description' => $this->compare($gallery->seo_description, $project->seo_description),
            'seo_image' => $this->compare($gallery->seo_image, $project->seo_image),
            'robots_index' => $this->compare((bool) $gallery->robots_index, (bool) $project->robots_index),
            'robots_follow' => $this->compare((bool) $gallery->robots_follow, (bool) $project->robots_follow),
            'published_at' => $this->compare($gallery->published_at?->toAtomString(), $project->published_at?->toAtomString()),
            'category' => $this->compare($gallery->category?->name, $project->category?->name),
        ];

        $media = $this->mediaDifferences($gallery, $project);
        $seoRecommendations = [];
        foreach (['seo_title', 'seo_description', 'seo_image', 'robots_index', 'robots_follow', 'published_at'] as $field) {
            $seoRecommendations[$field] = match ($fields[$field]) {
                'SAME' => 'NO_ACTION',
                'GALLERY_ONLY' => 'COPY_FROM_GALLERY',
                'PROJECT_ONLY' => 'KEEP_PROJECT',
                default => 'HUMAN_REVIEW',
            };
        }

        return [
            'fields' => $fields,
            'media' => $media,
            'seo_recommendations' => $seoRecommendations,
            'has_conflicts' => collect($fields)->contains(fn (string $state): bool => in_array($state, ['GALLERY_ONLY', 'DIFFERENT', 'NEEDS_REVIEW'], true))
                || $media['gallery_only_images'] !== []
                || $media['gallery_video_requires_migration'],
        ];
    }

    /** @return array<string, mixed> */
    private function mediaDifferences(Gallery $gallery, Project $project): array
    {
        $galleryMedia = collect([$gallery->featuredImage()])->filter()->merge($gallery->images());
        $projectMedia = collect([$project->featuredImage()])->filter()->merge($project->galleryImages());
        $projectKeys = $projectMedia->flatMap(fn ($media): array => [$media->getKey(), strtolower($media->file_name), $media->getPathRelativeToRoot()])->filter();
        $galleryKeys = $galleryMedia->flatMap(fn ($media): array => [$media->getKey(), strtolower($media->file_name), $media->getPathRelativeToRoot()])->filter();

        return [
            'gallery_featured' => $gallery->featuredImage()?->file_name,
            'project_featured' => $project->featuredImage()?->file_name,
            'already_represented_or_possible_duplicates' => $galleryMedia
                ->filter(fn ($media): bool => $projectKeys->contains($media->getKey()) || $projectKeys->contains(strtolower($media->file_name)) || $projectKeys->contains($media->getPathRelativeToRoot()))
                ->pluck('file_name')->values()->all(),
            'gallery_only_images' => $galleryMedia
                ->reject(fn ($media): bool => $projectKeys->contains($media->getKey()) || $projectKeys->contains(strtolower($media->file_name)) || $projectKeys->contains($media->getPathRelativeToRoot()))
                ->pluck('file_name')->values()->all(),
            'project_only_images' => $projectMedia
                ->reject(fn ($media): bool => $galleryKeys->contains($media->getKey()) || $galleryKeys->contains(strtolower($media->file_name)) || $galleryKeys->contains($media->getPathRelativeToRoot()))
                ->pluck('file_name')->values()->all(),
            'comparison_signals' => ['media_id', 'file_name', 'stored_relative_path'],
            'gallery_video_requires_migration' => filled($gallery->video_url)
                && ! $project->videos->contains(fn ($video): bool => trim($video->url) === trim((string) $gallery->video_url)),
        ];
    }

    /** @return array<string, mixed> */
    private function videoAnalysis(Gallery $gallery, ?Project $project): array
    {
        $resolved = filled($gallery->video_url)
            ? $this->videos->resolve((string) $gallery->video_url)
            : ['provider' => null, 'embed_url' => null, 'external_url' => null];
        $safe = blank($gallery->video_url) || $this->videos->isSafeExternalUrl($gallery->video_url);
        $matching = $project && filled($gallery->video_url)
            ? $project->videos->first(fn ($video) => trim($video->url) === trim((string) $gallery->video_url))
            : null;

        return [
            'has_video' => filled($gallery->video_url),
            'url' => $gallery->video_url,
            'provider' => $resolved['provider'],
            'embed_url' => $resolved['embed_url'],
            'safe_url' => $safe,
            'thumbnail_url' => $gallery->thumbnail_url,
            'matching_project_video_id' => $matching?->getKey(),
            'ready_for_project_video_migration' => filled($gallery->video_url) && $safe && ! $matching,
        ];
    }

    /** @return array<string, mixed> */
    private function taxonomyAnalysis(Gallery $gallery, ?Project $project, bool $schemaAvailable): array
    {
        $galleryCategory = $gallery->category?->name;
        $projectCategory = $project?->category?->name;
        $terms = $schemaAvailable && $project
            ? $project->discoveryTerms->map(fn ($term): array => [
                'vocabulary' => $term->vocabulary?->name,
                'term' => $term->name,
            ])->values()->all()
            : [];
        $suggestions = collect($terms)
            ->filter(fn (array $term): bool => $galleryCategory !== null
                && $this->normalize($term['term']) === $this->normalize($galleryCategory))
            ->values()->all();
        $legacyMediaCategoryRetired = in_array($gallery->category?->slug, ['project-photos', 'videos'], true);

        return [
            'gallery_category' => $galleryCategory,
            'project_category' => $projectCategory,
            'project_discovery_terms' => $terms,
            'possible_existing_term_mappings' => $suggestions,
            'discovery_schema_available' => $schemaAvailable,
            'migration_action' => $legacyMediaCategoryRetired ? 'NO_ACTION' : 'REVIEW',
            'mapping_required' => ! $legacyMediaCategoryRetired && $galleryCategory !== null && $suggestions === [],
        ];
    }

    /** @return array<string, mixed> */
    private function redirectAnalysis(Gallery $gallery, ?Project $project, Collection $redirects): array
    {
        $source = '/galleries/'.$gallery->slug;
        $target = $project ? '/projects/'.$project->slug : null;
        $existing = $redirects->get(Redirect::normalizePath($source));
        $existingTarget = $existing?->targetPath();

        return [
            'source' => $source,
            'proposed_target' => $target,
            'existing_redirect_id' => $existing?->getKey(),
            'existing_target' => $existingTarget,
            'existing_is_active' => $existing ? (bool) $existing->is_active : null,
            'existing_status_code' => $existing?->status_code,
            'target_is_published' => $project ? $this->isPubliclyPublished($project) : false,
            'loop' => $target !== null && Redirect::normalizePath($source) === Redirect::normalizePath($target),
            'conflict' => $existing !== null && $target !== null && Redirect::normalizePath((string) $existingTarget) !== Redirect::normalizePath($target),
            'safe_to_create_later' => $project !== null
                && $this->isPubliclyPublished($project)
                && ($existing === null || Redirect::normalizePath((string) $existingTarget) === Redirect::normalizePath($target)),
            'completed' => $existing !== null
                && (bool) $existing->is_active
                && $existing->status_code === 301
                && $target !== null
                && Redirect::normalizePath((string) $existingTarget) === Redirect::normalizePath($target),
        ];
    }

    private function compare(mixed $galleryValue, mixed $projectValue): string
    {
        $galleryFilled = filled($galleryValue);
        $projectFilled = filled($projectValue);

        return match (true) {
            ! $galleryFilled && ! $projectFilled => 'SAME',
            $galleryFilled && ! $projectFilled => 'GALLERY_ONLY',
            ! $galleryFilled && $projectFilled => 'PROJECT_ONLY',
            $this->normalizeValue($galleryValue) === $this->normalizeValue($projectValue) => 'SAME',
            default => 'DIFFERENT',
        };
    }

    /** @return array{id: int, title: string, slug: string, public_url: string, status: string} */
    private function projectSummary(Project $project): array
    {
        return [
            'id' => $project->getKey(),
            'title' => $project->title,
            'slug' => $project->slug,
            'public_url' => route('projects.show', $project->slug, absolute: false),
            'status' => $project->status,
        ];
    }

    private function normalize(?string $value): string
    {
        return Str::lower(Str::slug(Str::ascii(trim((string) $value)), ' '));
    }

    private function normalizeValue(mixed $value): string
    {
        return $value === null ? '' : preg_replace('/\s+/u', ' ', trim(strip_tags((string) $value)));
    }

    private function tokens(?string $value): Collection
    {
        return collect(preg_split('/\s+/u', $this->normalize($value), -1, PREG_SPLIT_NO_EMPTY))->unique();
    }

    private function isPubliclyPublished(Project $project): bool
    {
        return $project->status === 'published'
            && ($project->published_at === null || $project->published_at->isPast());
    }
}
