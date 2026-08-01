<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectMetricsFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_has_ordered_metric_entities(): void
    {
        $project = Project::factory()->create();

        $project->metrics()->createMany([
            [
                'label' => 'Completed units',
                'value' => '120',
                'suffix' => '+',
                'sort_order' => 2,
            ],
            [
                'label' => 'Delivery time',
                'value' => '10',
                'suffix' => ' weeks',
                'sort_order' => 1,
            ],
        ]);

        $this->assertSame(
            ['Delivery time', 'Completed units'],
            $project->metrics()->pluck('label')->all(),
        );
        $this->assertSame($project->id, $project->metrics()->firstOrFail()->project->id);
    }

    public function test_deleting_a_project_only_deletes_its_owned_metrics(): void
    {
        $deletedProject = Project::factory()->create();
        $retainedProject = Project::factory()->create();
        $deletedMetric = $deletedProject->metrics()->create([
            'label' => 'Deleted metric',
            'value' => '1',
        ]);
        $retainedMetric = $retainedProject->metrics()->create([
            'label' => 'Retained metric',
            'value' => '2',
        ]);

        $deletedProject->delete();

        $this->assertDatabaseMissing('project_metrics', ['id' => $deletedMetric->id]);
        $this->assertDatabaseHas('project_metrics', [
            'id' => $retainedMetric->id,
            'project_id' => $retainedProject->id,
        ]);
        $this->assertDatabaseHas('projects', ['id' => $retainedProject->id]);
    }

    public function test_admin_can_save_metrics_without_changing_legacy_project_data(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Metrics Project',
                'slug' => 'metrics-project',
                'content' => '<p>Legacy content</p>',
                'services' => [['name' => 'Legacy Service']],
                'attributes' => [['label' => 'Legacy Attribute', 'value' => 'Legacy Value']],
                'metrics' => [
                    [
                        'label' => 'Energy saved',
                        'value' => '35',
                        'prefix' => null,
                        'suffix' => '%',
                        'description' => 'Measured after project completion.',
                        'icon' => 'chart',
                        'sort_order' => 0,
                    ],
                ],
                'status' => 'draft',
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('slug', 'metrics-project')->firstOrFail();

        $this->assertSame('<p>Legacy content</p>', $project->content);
        $this->assertSame([['name' => 'Legacy Service']], $project->services);
        $this->assertSame(
            [['label' => 'Legacy Attribute', 'value' => 'Legacy Value']],
            $project->attributes,
        );
        $this->assertDatabaseHas('project_metrics', [
            'project_id' => $project->id,
            'label' => 'Energy saved',
            'value' => '35',
            'suffix' => '%',
            'icon' => 'chart',
        ]);
    }
}
