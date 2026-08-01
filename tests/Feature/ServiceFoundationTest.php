<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Service;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

class ServiceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_can_be_created_with_structured_foundation_content(): void
    {
        $service = Service::query()->create([
            'name' => 'Technical Consulting',
            'slug' => 'technical-consulting',
            'excerpt' => 'A concise service summary.',
            'overview' => '<p>A detailed service overview.</p>',
            'benefits' => [
                ['title' => 'Lower risk', 'description' => 'Identify delivery risks early.'],
            ],
            'process' => [
                ['title' => 'Discovery', 'description' => 'Review the current requirements.'],
            ],
            'deliverables' => [
                ['title' => 'Assessment report'],
            ],
            'status' => Service::STATUS_DRAFT,
            'published_at' => now(),
            'sort_order' => 10,
            'seo_title' => 'Technical Consulting Service',
            'seo_description' => 'Professional technical consulting.',
            'icon' => 'heroicon-o-light-bulb',
        ]);

        $service = $service->fresh();

        $this->assertSame('Technical Consulting', $service->name);
        $this->assertSame('Lower risk', $service->benefits[0]['title']);
        $this->assertSame('Discovery', $service->process[0]['title']);
        $this->assertSame('Assessment report', $service->deliverables[0]['title']);
        $this->assertNotNull($service->published_at);
        $this->assertDatabaseHas('services', [
            'slug' => 'technical-consulting',
            'status' => Service::STATUS_DRAFT,
            'seo_title' => 'Technical Consulting Service',
        ]);
    }

    public function test_service_slug_must_be_unique(): void
    {
        Service::query()->create([
            'name' => 'First Service',
            'slug' => 'shared-service-slug',
        ]);

        $this->expectException(QueryException::class);

        Service::query()->create([
            'name' => 'Second Service',
            'slug' => 'shared-service-slug',
        ]);
    }

    public function test_published_scope_supports_modern_and_legacy_lifecycle_states(): void
    {
        $legacyActive = $this->service('legacy-active', Service::STATUS_ACTIVE);
        $modernPublished = $this->service('modern-published', Service::STATUS_PUBLISHED, now()->subMinute());
        $unscheduledPublished = $this->service('unscheduled-published', Service::STATUS_PUBLISHED);
        $futurePublished = $this->service('future-published', Service::STATUS_PUBLISHED, now()->addDay());
        $legacyInactive = $this->service('legacy-inactive', Service::STATUS_INACTIVE);
        $draft = $this->service('draft-service', Service::STATUS_DRAFT);
        $archived = $this->service('archived-service', Service::STATUS_ARCHIVED);

        $publishedIds = Service::query()->published()->pluck('id');

        $this->assertTrue($publishedIds->contains($legacyActive->id));
        $this->assertTrue($publishedIds->contains($modernPublished->id));
        $this->assertTrue($publishedIds->contains($unscheduledPublished->id));
        $this->assertFalse($publishedIds->contains($futurePublished->id));
        $this->assertFalse($publishedIds->contains($legacyInactive->id));
        $this->assertFalse($publishedIds->contains($draft->id));
        $this->assertFalse($publishedIds->contains($archived->id));
        $this->assertSame(
            $publishedIds->sort()->values()->all(),
            Service::query()->active()->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_existing_project_relation_remains_compatible(): void
    {
        $project = Project::factory()->create([
            'services' => [['name' => 'Legacy JSON Service']],
        ]);
        $service = $this->service('structured-service', Service::STATUS_ACTIVE);

        $project->relatedServices()->attach($service);

        $this->assertTrue($project->is($service->projects()->firstOrFail()));
        $this->assertSame(['Structured Service'], $project->serviceNames()->all());
        $this->assertSame([['name' => 'Legacy JSON Service']], $project->fresh()->services);
        $this->assertDatabaseHas('project_service', [
            'project_id' => $project->id,
            'service_id' => $service->id,
        ]);
    }

    public function test_service_uses_the_shared_featured_image_and_gallery_contract(): void
    {
        $service = $this->service('media-service', Service::STATUS_DRAFT);
        $collections = $service->getRegisteredMediaCollections()->keyBy('name');

        $this->assertInstanceOf(HasMedia::class, $service);
        $this->assertSame(['featured_image', 'gallery'], $collections->keys()->all());
        $this->assertTrue($collections->get('featured_image')->singleFile);
        $this->assertFalse($collections->get('gallery')->singleFile);
        $this->assertNull($service->featuredImage());
        $this->assertTrue($service->galleryImages()->isEmpty());
    }

    private function service(string $slug, string $status, mixed $publishedAt = null): Service
    {
        return Service::query()->create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }
}
