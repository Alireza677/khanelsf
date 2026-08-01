<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectServicesRelationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_service_names_fall_back_to_legacy_json(): void
    {
        $project = Project::factory()->create([
            'services' => [
                ['name' => 'Legacy Architecture'],
                ['name' => 'Legacy Supervision'],
            ],
        ]);

        $this->assertSame(
            ['Legacy Architecture', 'Legacy Supervision'],
            $project->serviceNames()->all(),
        );
    }

    public function test_related_services_take_priority_over_legacy_json(): void
    {
        $project = Project::factory()->create([
            'services' => [['name' => 'Legacy Service']],
        ]);
        $architecture = Service::query()->create([
            'name' => 'Architecture',
            'slug' => 'architecture',
            'status' => 'active',
            'sort_order' => 1,
        ]);
        $consulting = Service::query()->create([
            'name' => 'Consulting',
            'slug' => 'consulting',
            'status' => 'active',
            'sort_order' => 2,
        ]);

        $project->relatedServices()->sync([$consulting->id, $architecture->id]);
        $project->unsetRelation('relatedServices');

        $this->assertSame(['Architecture', 'Consulting'], $project->serviceNames()->all());
        $this->assertDatabaseHas('project_service', [
            'project_id' => $project->id,
            'service_id' => $architecture->id,
        ]);
        $this->assertDatabaseHas('project_service', [
            'project_id' => $project->id,
            'service_id' => $consulting->id,
        ]);
    }

    public function test_admin_can_save_related_and_legacy_services_together(): void
    {
        $this->actingAs(User::factory()->create());
        $service = Service::query()->create([
            'name' => 'Architecture',
            'slug' => 'architecture',
            'status' => 'active',
            'sort_order' => 0,
        ]);

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Service Relation Project',
                'slug' => 'service-relation-project',
                'services' => [['name' => 'Legacy Consulting']],
                'attributes' => [],
                'relatedServices' => [$service->id],
                'status' => 'draft',
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('slug', 'service-relation-project')->firstOrFail();

        $this->assertSame([['name' => 'Legacy Consulting']], $project->services);
        $this->assertSame(['Architecture'], $project->serviceNames()->all());
        $this->assertDatabaseHas('project_service', [
            'project_id' => $project->id,
            'service_id' => $service->id,
        ]);
    }
}
