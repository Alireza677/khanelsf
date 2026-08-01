<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectCaseStudyFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_project_with_case_study_and_legacy_data(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Case Study Project',
                'slug' => 'case-study-project',
                'excerpt' => 'A project upgraded without removing its legacy content.',
                'content' => '<p>Legacy project content remains available.</p>',
                'client_name' => 'Example Client',
                'location' => 'Tehran',
                'industry' => 'Construction',
                'project_type' => 'Commercial',
                'project_date' => '2026-01-01',
                'project_started_at' => '2026-01-10',
                'project_completed_at' => '2026-03-20',
                'challenge' => 'The project had a complex delivery constraint.',
                'solution' => 'The team introduced a staged delivery plan.',
                'results_summary' => 'The project was completed within the target window.',
                'client_quote' => 'The delivery process was clear and dependable.',
                'services' => [['name' => 'Architecture']],
                'attributes' => [['label' => 'Timeline', 'value' => '10 weeks']],
                'status' => 'draft',
                'sort_order' => 0,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = Project::query()->where('slug', 'case-study-project')->firstOrFail();

        $this->assertSame('2026-01-10', $project->project_started_at?->toDateString());
        $this->assertSame('2026-03-20', $project->project_completed_at?->toDateString());
        $this->assertSame([['name' => 'Architecture']], $project->services);
        $this->assertSame([['label' => 'Timeline', 'value' => '10 weeks']], $project->attributes);
        $this->assertTrue($project->hasCaseStudyData());
        $this->assertDatabaseHas('projects', [
            'slug' => 'case-study-project',
            'industry' => 'Construction',
            'project_type' => 'Commercial',
            'challenge' => 'The project had a complex delivery constraint.',
            'solution' => 'The team introduced a staged delivery plan.',
        ]);
    }

    public function test_existing_project_shape_remains_valid_without_case_study_data(): void
    {
        $project = Project::factory()->create();

        $this->assertNull($project->project_started_at);
        $this->assertNull($project->project_completed_at);
        $this->assertFalse($project->hasCaseStudyData());
        $this->assertNotNull($project->content);
        $this->assertNotEmpty($project->services);
        $this->assertNotEmpty($project->attributes);
        $this->assertNotNull($project->project_date);
    }
}
