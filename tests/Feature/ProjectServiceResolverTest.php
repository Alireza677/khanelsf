<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Service;
use App\Services\ProjectServiceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectServiceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_relation_is_canonical_and_supports_modern_and_legacy_statuses(): void
    {
        $project = Project::factory()->create([
            'services' => [['name' => 'Legacy JSON Service']],
        ]);
        $active = $this->service('Legacy Active', 'legacy-active', Service::STATUS_ACTIVE, 2);
        $published = $this->service('Modern Published', 'modern-published', Service::STATUS_PUBLISHED, 1);
        $project->relatedServices()->attach([$active->id, $published->id]);

        $this->assertSame(
            ['Modern Published', 'Legacy Active'],
            app(ProjectServiceResolver::class)->names($project)->all(),
        );
        $this->assertSame(
            ['Modern Published', 'Legacy Active'],
            $project->serviceNames()->all(),
        );
        $this->assertSame([['name' => 'Legacy JSON Service']], $project->fresh()->services);
    }

    public function test_legacy_json_is_used_when_no_related_service_is_public(): void
    {
        $project = Project::factory()->create([
            'services' => [
                ['name' => '  Legacy Architecture  '],
                ['name' => ''],
            ],
        ]);
        $draft = $this->service('Draft Service', 'draft-service', Service::STATUS_DRAFT);
        $inactive = $this->service('Inactive Service', 'inactive-service', Service::STATUS_INACTIVE);
        $future = $this->service(
            'Future Service',
            'future-service',
            Service::STATUS_PUBLISHED,
            publishedAt: now()->addDay(),
        );
        $project->relatedServices()->attach([$draft->id, $inactive->id, $future->id]);

        $this->assertSame(
            ['Legacy Architecture'],
            app(ProjectServiceResolver::class)->names($project)->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$draft->id, $inactive->id, $future->id],
            $project->relatedServices()->pluck('services.id')->all(),
        );
        $this->assertSame(
            [
                ['name' => '  Legacy Architecture  '],
                ['name' => ''],
            ],
            $project->fresh()->services,
        );
    }

    public function test_loaded_relation_is_filtered_without_lazy_loading(): void
    {
        $project = Project::factory()->make([
            'services' => [['name' => 'Legacy Fallback']],
        ]);
        $project->setRelation('relatedServices', collect([
            new Service([
                'name' => 'Published Service',
                'status' => Service::STATUS_PUBLISHED,
                'published_at' => now()->subMinute(),
            ]),
            new Service([
                'name' => 'Inactive Service',
                'status' => Service::STATUS_INACTIVE,
            ]),
        ]));

        $this->assertSame(
            ['Published Service'],
            app(ProjectServiceResolver::class)->names($project)->all(),
        );
        $this->assertTrue($project->relationLoaded('relatedServices'));
    }

    private function service(
        string $name,
        string $slug,
        string $status,
        int $sortOrder = 0,
        mixed $publishedAt = null,
    ): Service {
        return Service::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'sort_order' => $sortOrder,
            'published_at' => $publishedAt,
        ]);
    }
}
