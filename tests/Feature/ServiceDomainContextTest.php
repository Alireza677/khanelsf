<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Services\SeoService;
use App\Services\ServiceMediaService;
use App\Services\ServiceQueryService;
use App\Services\ServiceTemplateContextBuilder;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceDomainContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_detail_query_supports_legacy_and_modern_publication_states(): void
    {
        $legacyActive = $this->service('legacy-active', Service::STATUS_ACTIVE);
        $publishedWithoutDate = $this->service('published-without-date', Service::STATUS_PUBLISHED);
        $publishedInPast = $this->service('published-in-past', Service::STATUS_PUBLISHED, now()->subMinute());
        $draft = $this->service('draft', Service::STATUS_DRAFT);
        $inactive = $this->service('inactive', Service::STATUS_INACTIVE);
        $archived = $this->service('archived', Service::STATUS_ARCHIVED);
        $future = $this->service('future', Service::STATUS_PUBLISHED, now()->addDay());
        $queries = app(ServiceQueryService::class);

        foreach ([$legacyActive, $publishedWithoutDate, $publishedInPast] as $publicService) {
            $found = $queries->findPublishedBySlug($publicService->slug);

            $this->assertTrue($publicService->is($found));
            $this->assertTrue($found->relationLoaded('media'));
            $this->assertTrue($found->relationLoaded('publicProjects'));
        }

        foreach ([$draft, $inactive, $archived, $future] as $hiddenService) {
            $this->assertNull($queries->findPublishedBySlug($hiddenService->slug));
        }

        $this->assertTrue($draft->is($queries->findForAdminBySlug($draft->slug)));
        $this->assertNull($queries->findPublishedBySlug('missing-service'));
    }

    public function test_archive_query_has_stable_public_ordering(): void
    {
        $secondByName = $this->service('zeta-service', Service::STATUS_PUBLISHED, sortOrder: 1);
        $firstByName = $this->service('alpha-service', Service::STATUS_ACTIVE, sortOrder: 1);
        $firstBySort = $this->service('priority-service', Service::STATUS_PUBLISHED, sortOrder: 0);
        $this->service('hidden-draft', Service::STATUS_DRAFT, sortOrder: 0);

        $this->assertSame(
            [$firstBySort->id, $firstByName->id, $secondByName->id],
            app(ServiceQueryService::class)->archiveQuery()->pluck('id')->all(),
        );
    }

    public function test_related_projects_include_only_public_projects_with_eager_loaded_card_relations(): void
    {
        $category = ProjectCategory::factory()->create();
        $service = $this->service('project-service', Service::STATUS_PUBLISHED);
        $publicProject = Project::factory()->published()->create([
            'project_category_id' => $category->id,
            'sort_order' => 2,
        ]);
        $firstPublicProject = Project::factory()->published()->create([
            'project_category_id' => $category->id,
            'sort_order' => 1,
        ]);
        $draftProject = Project::factory()->create([
            'project_category_id' => $category->id,
            'status' => 'draft',
        ]);
        $futureProject = Project::factory()->create([
            'project_category_id' => $category->id,
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);
        $service->projects()->attach([
            $publicProject->id,
            $firstPublicProject->id,
            $draftProject->id,
            $futureProject->id,
        ]);

        $found = app(ServiceQueryService::class)->findPublishedBySlug($service->slug);
        $projects = app(ServiceQueryService::class)->relatedProjects($found);

        $this->assertSame(
            [$firstPublicProject->id, $publicProject->id],
            $projects->modelKeys(),
        );
        $this->assertTrue($found->relationLoaded('publicProjects'));

        foreach ($projects as $project) {
            $this->assertTrue($project->relationLoaded('category'));
            $this->assertTrue($project->relationLoaded('media'));
        }
    }

    public function test_media_context_resolves_featured_gallery_deduplication_and_seo_image(): void
    {
        Storage::fake('public');
        $service = $this->service('media-context', Service::STATUS_PUBLISHED);
        $featured = $service
            ->addMedia(UploadedFile::fake()->image('featured.jpg'))
            ->withCustomProperties(['source_media_id' => 100])
            ->toMediaCollection('featured_image', 'public');
        $service
            ->addMedia(UploadedFile::fake()->image('featured-duplicate.jpg'))
            ->withCustomProperties(['source_media_id' => 100])
            ->toMediaCollection('gallery', 'public');
        $gallery = $service
            ->addMedia(UploadedFile::fake()->image('gallery.jpg'))
            ->withCustomProperties(['source_media_id' => 200])
            ->toMediaCollection('gallery', 'public');
        $service
            ->addMedia(UploadedFile::fake()->image('gallery-duplicate.jpg'))
            ->withCustomProperties(['source_media_id' => 200])
            ->toMediaCollection('gallery', 'public');

        $media = app(ServiceMediaService::class)->context($service->fresh());

        $this->assertSame($featured->id, $media['featured']['id']);
        $this->assertSame([$gallery->id], $media['gallery']->pluck('id')->all());
        $this->assertSame($featured->getUrl(), $media['seo_image']);
    }

    public function test_media_context_uses_gallery_then_global_fallback_and_is_safe_without_media(): void
    {
        Storage::fake('public');
        $galleryOnly = $this->service('gallery-only', Service::STATUS_PUBLISHED);
        $gallery = $galleryOnly
            ->addMedia(UploadedFile::fake()->image('gallery.jpg'))
            ->toMediaCollection('gallery', 'public');
        $galleryContext = app(ServiceMediaService::class)->context($galleryOnly->fresh());

        $this->assertNull($galleryContext['featured']);
        $this->assertSame($gallery->getUrl(), $galleryContext['seo_image']);

        app(SettingsService::class)->set(
            'default_og_image',
            '/images/default-service.jpg',
            'seo',
            'image',
        );
        $empty = $this->service('empty-media', Service::STATUS_PUBLISHED);
        $emptyContext = app(ServiceMediaService::class)->context($empty);

        $this->assertNull($emptyContext['featured']);
        $this->assertTrue($emptyContext['gallery']->isEmpty());
        $this->assertSame('/images/default-service.jpg', $emptyContext['seo_image']);
    }

    public function test_service_seo_uses_canonical_fallbacks_with_service_and_breadcrumb_schema(): void
    {
        app(SettingsService::class)->set(
            'default_og_image',
            '/images/default-og.jpg',
            'seo',
            'image',
        );
        $service = $this->service('seo-service', Service::STATUS_PUBLISHED, attributes: [
            'name' => 'Canonical Service Name',
            'excerpt' => 'Excerpt fallback.',
            'overview' => '<p>Overview fallback should not win.</p>',
        ]);
        $seo = app(SeoService::class)->forService($service);

        $this->assertSame('Canonical Service Name', $seo->title);
        $this->assertSame('Excerpt fallback.', $seo->description);
        $this->assertSame(url('/services/seo-service'), $seo->canonicalUrl);
        $this->assertSame('index, follow', $seo->robots);
        $this->assertSame('Canonical Service Name', $seo->openGraphTitle());
        $this->assertSame('Excerpt fallback.', $seo->openGraphDescription());
        $this->assertSame(url('/images/default-og.jpg'), $seo->ogImage);
        $this->assertSame('summary_large_image', $seo->twitterCard);
        $serviceSchema = collect($seo->schema['@graph'])->firstWhere('@type', 'Service');
        $breadcrumbSchema = collect($seo->schema['@graph'])->firstWhere('@type', 'BreadcrumbList');

        $this->assertSame('Canonical Service Name', $serviceSchema['name']);
        $this->assertSame(url('/services/seo-service').'#service', $serviceSchema['@id']);
        $this->assertSame('Organization', $serviceSchema['provider']['@type']);
        $this->assertSame(
            [url('/'), url('/services'), url('/services/seo-service')],
            collect($breadcrumbSchema['itemListElement'])->pluck('item')->all(),
        );

        $service->update([
            'seo_title' => 'Explicit SEO title',
            'seo_description' => 'Explicit SEO description.',
        ]);
        $explicit = app(SeoService::class)->forService($service->fresh());

        $this->assertSame('Explicit SEO title', $explicit->title);
        $this->assertSame('Explicit SEO description.', $explicit->description);
    }

    public function test_service_seo_falls_back_to_controlled_overview_and_noindexes_non_public_content(): void
    {
        $draft = $this->service('draft-seo', Service::STATUS_DRAFT, attributes: [
            'excerpt' => null,
            'overview' => '<p>'.str_repeat('Long overview ', 30).'</p>',
        ]);
        $seo = app(SeoService::class)->forService($draft);

        $this->assertSame(160, mb_strlen($seo->description));
        $this->assertStringNotContainsString('<p>', $seo->description);
        $this->assertSame('noindex, nofollow', $seo->robots);
    }

    public function test_context_builder_returns_the_complete_canonical_shape(): void
    {
        Storage::fake('public');
        $service = $this->service('context-service', Service::STATUS_PUBLISHED, attributes: [
            'excerpt' => 'Context excerpt.',
            'overview' => '<p>Context overview.</p>',
            'benefits' => [['title' => 'Benefit', 'description' => null, 'icon' => null]],
            'process' => [
                ['title' => 'Discovery', 'description' => null, 'step' => 99],
                ['title' => 'Delivery', 'description' => null, 'step' => 99],
            ],
            'deliverables' => [['title' => 'Report', 'description' => null]],
            'icon' => 'heroicon-o-star',
        ]);
        $project = Project::factory()->published()->create();
        $service->projects()->attach($project);
        $featured = $service
            ->addMedia(UploadedFile::fake()->image('featured.jpg'))
            ->toMediaCollection('featured_image', 'public');

        $prepared = app(ServiceQueryService::class)->findPublishedBySlug($service->slug);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $context = app(ServiceTemplateContextBuilder::class)->build($prepared);
        $queryCountAfterBuild = count(DB::getQueryLog());

        $this->assertSame([
            'entity',
            'service',
            'content',
            'media',
            'projects',
            'relatedServices',
            'seo',
            'templateContext',
        ], array_keys($context));
        $this->assertTrue($service->is($context['entity']));
        $this->assertTrue($service->is($context['service']));
        $this->assertSame(['Benefit'], collect($context['content']['benefits'])->pluck('title')->all());
        $this->assertSame([1, 2], collect($context['content']['process'])->pluck('step')->all());
        $this->assertSame(['Report'], collect($context['content']['deliverables'])->pluck('title')->all());
        $this->assertSame($featured->getUrl(), $context['media']['featured']['url']);
        $this->assertSame([$project->id], $context['projects']->modelKeys());
        $this->assertTrue($context['relatedServices']->isEmpty());
        $this->assertSame('service', $context['templateContext']['type']);
        $this->assertSame('service_single', $context['templateContext']['target']);

        $context['media']['gallery']->all();
        $context['projects']->each(function (Project $project): void {
            $project->category;
            $project->media;
        });
        $context['relatedServices']->all();
        $context['seo']->metaTitle();

        $this->assertSame($queryCountAfterBuild, count(DB::getQueryLog()));
        $this->assertSame(0, $queryCountAfterBuild);
    }

    public function test_context_builder_supplies_safe_empty_values(): void
    {
        $service = $this->service('empty-context', Service::STATUS_ACTIVE, attributes: [
            'benefits' => null,
            'process' => null,
            'deliverables' => null,
        ]);

        $context = app(ServiceTemplateContextBuilder::class)->build($service);

        $this->assertSame([], $context['content']['benefits']);
        $this->assertSame([], $context['content']['process']);
        $this->assertSame([], $context['content']['deliverables']);
        $this->assertTrue($context['projects']->isEmpty());
        $this->assertTrue($context['relatedServices']->isEmpty());
        $this->assertTrue($context['media']['gallery']->isEmpty());
    }

    private function service(
        string $slug,
        string $status,
        mixed $publishedAt = null,
        int $sortOrder = 0,
        array $attributes = [],
    ): Service {
        return Service::query()->create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'status' => $status,
            'published_at' => $publishedAt,
            'sort_order' => $sortOrder,
            ...$attributes,
        ]);
    }
}
