<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Project;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;

final class LegacyGalleryMigrationAuditService
{
    public function __construct(
        private readonly LegacyGalleryMigrationAnalyzer $analyzer,
        private readonly ModuleService $modules,
    ) {}

    /** @return array<string, mixed> */
    public function audit(?int $galleryId = null): array
    {
        $discoverySchema = Schema::hasTable('project_discovery_vocabularies')
            && Schema::hasTable('project_discovery_terms')
            && Schema::hasTable('project_discovery_term_project');

        $projectsQuery = Project::query()->with(['category', 'media', 'videos']);
        if ($discoverySchema) {
            $projectsQuery->with('discoveryTerms.vocabulary');
        }
        $projects = $projectsQuery->get();
        $redirects = Redirect::query()->get()->keyBy(fn (Redirect $redirect): string => $redirect->source_path);
        $references = $this->storedReferences();

        $query = Gallery::query()->with(['category', 'media'])->orderBy('id');
        if ($galleryId !== null) {
            $query->whereKey($galleryId);
        }
        $galleries = $query->get()->map(fn (Gallery $gallery): array => $this->analyzer->analyze(
            $gallery,
            $projects,
            $redirects,
            $references,
            $discoverySchema,
        ));

        return [
            'generated_at' => now()->toAtomString(),
            'read_only' => true,
            'schema' => ['project_discovery_taxonomy_available' => $discoverySchema],
            'totals' => $this->totals($galleries),
            'galleries' => $galleries->values()->all(),
            'url_references' => $this->referenceSummary($references),
            'templates' => $this->templateAudit(),
            'categories' => $this->categoryAudit($projects, $discoverySchema),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $items */
    public function totals(Collection $items): array
    {
        return [
            'total_galleries' => $items->count(),
            'class_a' => $items->where('business_class', 'A')->count(),
            'broken_project_references' => $items->where('broken_project_reference', true)->count(),
            'potential_b' => $items->where('suggested_class', 'B')->count(),
            'potential_c' => $items->where('suggested_class', 'C')->count(),
            'video_or_mixed' => $items->where('media_class', 'D')->count(),
            'needs_human_review' => $items->where('human_review_required', true)->count(),
            'ready' => $items->where('readiness', 'READY')->count(),
            'review_required' => $items->where('readiness', 'REVIEW_REQUIRED')->count(),
            'blocked' => $items->where('readiness', 'BLOCKED')->count(),
        ];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function storedReferences(): array
    {
        $references = [];
        $sources = [
            ['model' => MenuItem::class, 'columns' => ['url'], 'label' => 'menu_items'],
            ['model' => Page::class, 'columns' => ['content', 'blocks'], 'label' => 'pages'],
            ['model' => Post::class, 'columns' => ['content'], 'label' => 'posts'],
            ['model' => Template::class, 'columns' => ['blocks'], 'label' => 'templates'],
            ['model' => Setting::class, 'columns' => ['value'], 'label' => 'settings'],
        ];

        foreach ($sources as $source) {
            $table = (new $source['model'])->getTable();
            if (! Schema::hasTable($table)) {
                continue;
            }
            $columns = collect($source['columns'])->filter(fn (string $column): bool => Schema::hasColumn($table, $column))->all();
            if ($columns === []) {
                continue;
            }
            foreach ($source['model']::query()->select(['id', ...$columns])->get() as $record) {
                foreach ($columns as $column) {
                    $value = is_scalar($record->{$column}) ? (string) $record->{$column} : json_encode($record->{$column});
                    preg_match_all('~(?<![\\w-])/galleries/([\\pL\\pN_-]+)~u', (string) $value, $matches);
                    foreach (array_unique($matches[1] ?? []) as $slug) {
                        if ($slug === 'category') {
                            continue;
                        }
                        $references[$slug][] = ['source' => $source['label'], 'record_id' => $record->getKey(), 'column' => $column];
                    }
                }
            }
        }

        foreach (['app', 'config', 'database', 'resources/views', 'routes'] as $directory) {
            $path = base_path($directory);
            if (! File::isDirectory($path)) {
                continue;
            }
            foreach (File::allFiles($path) as $file) {
                if (! in_array(strtolower($file->getExtension()), ['php', 'blade', 'json', 'js', 'ts'], true)) {
                    continue;
                }
                preg_match_all('~(?<![\\w-])/galleries/([\\pL\\pN_-]+)~u', File::get($file->getPathname()), $matches);
                foreach (array_unique($matches[1] ?? []) as $slug) {
                    if ($slug === 'category' || str_contains($slug, '{')) {
                        continue;
                    }
                    $references[$slug][] = ['source' => 'source_file', 'path' => str_replace('\\\\', '/', $file->getRelativePathname()), 'root' => $directory];
                }
            }
        }

        ksort($references);
        return $references;
    }

    private function referenceSummary(array $references): array
    {
        return [
            'archive_url_preserved' => '/galleries',
            'detail_reference_count' => collect($references)->flatten(1)->count(),
            'referenced_detail_slugs' => count($references),
            'references_by_slug' => $references,
            'scope' => 'persisted menu/page/post/template/setting fields plus app/config/database/views/routes source files',
        ];
    }

    private function templateAudit(): array
    {
        $types = ['galleries_index', 'gallery_category', 'gallery_single'];
        $existingGalleryIds = Gallery::query()->pluck('id')->all();
        $existingCategoryIds = GalleryCategory::query()->pluck('id')->all();

        return Template::query()->whereIn('type', $types)->orderBy('type')->orderBy('id')->get()->map(function (Template $template) use ($existingGalleryIds, $existingCategoryIds): array {
            $condition = $template->conditions ?? ['type' => 'all'];
            $targetId = $condition['item_id'] ?? $condition['category_id'] ?? null;
            $exists = match ($condition['type'] ?? 'all') {
                'specific_item' => in_array((int) $targetId, $existingGalleryIds, true),
                'category' => in_array((int) $targetId, $existingCategoryIds, true),
                default => true,
            };
            return [
                'id' => $template->getKey(), 'title' => $template->title, 'type' => $template->type,
                'status' => $template->status, 'is_default' => (bool) $template->is_default,
                'priority' => $template->priority, 'condition' => $condition,
                'condition_target_exists' => $exists, 'potentially_orphaned_after_gallery_detail_retirement' => $template->type === 'gallery_single',
            ];
        })->values()->all();
    }

    private function categoryAudit(Collection $projects, bool $discoverySchema): array
    {
        $sitemapEnabled = $this->modules->galleriesEnabled();
        return GalleryCategory::query()->with(['galleries.project'])->orderBy('id')->get()->map(function (GalleryCategory $category) use ($projects, $discoverySchema, $sitemapEnabled): array {
            $published = $category->galleries->filter(fn (Gallery $gallery): bool => $gallery->status === 'published' && ($gallery->published_at === null || $gallery->published_at->isPast()));
            $terms = $discoverySchema ? $projects->flatMap->discoveryTerms->filter(fn ($term): bool => mb_strtolower(trim($term->name)) === mb_strtolower(trim($category->name)))->unique('id') : collect();
            return [
                'id' => $category->getKey(), 'name' => $category->name, 'slug' => $category->slug,
                'active' => $category->status === 'active', 'gallery_count' => $category->galleries->count(),
                'published_gallery_count' => $published->count(),
                'galleries' => $category->galleries->map(fn (Gallery $gallery): array => ['id' => $gallery->getKey(), 'title' => $gallery->title, 'project_id' => $gallery->project_id])->values()->all(),
                'mapped_to_projects' => $category->galleries->whereNotNull('project_id')->count(),
                'possible_discovery_terms' => $terms->map(fn ($term): array => ['id' => $term->getKey(), 'name' => $term->name, 'vocabulary' => $term->vocabulary?->name])->values()->all(),
                'currently_in_sitemap' => $sitemapEnabled && $category->status === 'active' && (bool) $category->robots_index,
            ];
        })->values()->all();
    }
}
