<?php

namespace Tests\Feature;

use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTemplateRuntimeHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_case_study_recipe_can_be_reviewed_published_and_selected_in_production(): void
    {
        $project = Project::factory()->published()->create();
        $template = app(TemplateRecipeInstantiator::class)->createDraft('project_case_study', [
            'slug' => 'project-case-study-production',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
        ]);

        $this->assertSame('draft', $template->status);
        $this->assertSame('project_single', $template->type);
        $this->assertCount(8, $template->blocks);
        $this->assertCount(
            8,
            collect($template->blocks)->pluck('data.block_id')->filter()->unique(),
        );

        $template->update(['status' => 'published']);

        $this->assertTrue($template->fresh()->is_default);
        $this->assertSame(
            $template->id,
            app(TemplateService::class)
                ->findTemplateFor('project_single', $project)
                ?->id,
        );
    }

    public function test_registered_project_blocks_render_with_eager_loaded_runtime_context(): void
    {
        Storage::fake('public');
        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->published()->create([
            'project_category_id' => $category->id,
            'client_name' => 'Runtime Client',
            'challenge' => 'Runtime Challenge',
        ]);
        $related = Project::factory()->published()->create([
            'project_category_id' => $category->id,
            'title' => 'Runtime Related Project',
        ]);
        $service = Service::query()->create([
            'name' => 'Runtime Service',
            'slug' => 'runtime-service',
            'status' => 'active',
        ]);
        $project->relatedServices()->attach($service);
        $project->metrics()->create([
            'label' => 'Runtime Metric',
            'value' => '42',
        ]);
        $this->addMedia($project, 'runtime-featured.jpg', 'featured_image');
        $this->addMedia($project, 'runtime-gallery.jpg', 'gallery');
        $template = app(TemplateRecipeInstantiator::class)->createDraft('project_case_study', [
            'slug' => 'complete-project-template',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
        ]);
        $template->update(['status' => 'published']);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee('shared-hero--cover', false)
            ->assertSee('project-overview__facts', false)
            ->assertSee('project-story__section', false)
            ->assertSee('project-achievements__grid', false)
            ->assertSee('project-gallery--editorial', false)
            ->assertSee('project-services__list', false)
            ->assertSee('related-projects__grid', false)
            ->assertSee('آماده شروع پروژه بعدی شما هستیم')
            ->assertSee('Runtime Client')
            ->assertSee('Runtime Challenge')
            ->assertSee('Runtime Metric')
            ->assertSee('Runtime Service')
            ->assertSee('runtime-featured', false)
            ->assertSee('runtime-gallery', false)
            ->assertSee($related->title);
    }

    public function test_project_template_preview_uses_real_relations_media_and_related_projects(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->published()->create([
            'project_category_id' => $category->id,
            'client_name' => 'Preview Client',
        ]);
        Project::factory()->published()->create([
            'project_category_id' => $category->id,
            'title' => 'Preview Related Project',
        ]);
        $service = Service::query()->create([
            'name' => 'Preview Service',
            'slug' => 'preview-service',
            'status' => 'active',
        ]);
        $project->relatedServices()->attach($service);
        $project->metrics()->create([
            'label' => 'Preview Metric',
            'value' => '88',
        ]);
        $this->addMedia($project, 'preview-gallery.jpg', 'gallery');
        $template = $this->projectTemplate(status: 'draft');

        $this->actingAs($admin)
            ->get(route('admin.preview.templates.show', [
                'template' => $template,
                'context_id' => $project->id,
            ]))
            ->assertOk()
            ->assertSee('Preview Client')
            ->assertSee('Preview Metric')
            ->assertSee('Preview Service')
            ->assertSee('preview-gallery', false)
            ->assertSee('Preview Related Project');
    }

    public function test_direct_project_preview_and_production_use_the_same_template_runtime(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->published()->create([
            'client_name' => 'Shared Runtime Client',
        ]);
        $template = app(TemplateRecipeInstantiator::class)->createDraft('project_case_study', [
            'slug' => 'legacy-compatible-project-template',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
        ]);
        $template->update(['status' => 'published']);

        $production = $this->get(route('projects.show', $project->slug));
        $preview = $this->actingAs($admin)
            ->get(route('admin.preview.projects.show', $project));

        $production->assertOk()->assertSee('Shared Runtime Client');
        $preview->assertOk()->assertSee('Shared Runtime Client');
        $this->assertStringContainsString('content-block project-section', $production->getContent());
        $this->assertStringContainsString('content-block project-section', $preview->getContent());
        $this->assertStringNotContainsString('project-detail', $production->getContent());
        $this->assertStringNotContainsString('project-detail', $preview->getContent());
    }

    public function test_related_projects_runtime_honors_template_limit_up_to_six(): void
    {
        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->published()->create([
            'project_category_id' => $category->id,
        ]);
        $related = Project::factory()
            ->count(6)
            ->published()
            ->create(['project_category_id' => $category->id]);

        Template::query()->create([
            'title' => 'Six related projects',
            'slug' => 'six-related-projects',
            'type' => 'project_single',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [[
                'type' => 'related_projects',
                'data' => ['settings' => ['limit' => 6]],
            ]],
        ]);

        $response = $this->get(route('projects.show', $project->slug))->assertOk();

        foreach ($related as $relatedProject) {
            $response->assertSee($relatedProject->title);
        }
    }

    public function test_incomplete_and_legacy_projects_render_without_errors(): void
    {
        $template = app(TemplateRecipeInstantiator::class)->createDraft('project_case_study', [
            'slug' => 'incomplete-and-legacy-project-template',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
        ]);
        $template->update(['status' => 'published']);
        $incomplete = Project::factory()->published()->create([
            'project_category_id' => null,
            'excerpt' => null,
            'content' => null,
            'client_name' => null,
            'location' => null,
            'project_date' => null,
            'services' => null,
            'attributes' => null,
        ]);
        $legacy = Project::factory()->published()->create([
            'project_category_id' => null,
            'content' => '<p>Legacy production content</p>',
            'services' => [['name' => 'Legacy production service']],
            'attributes' => [['label' => 'Legacy field', 'value' => 'Legacy value']],
            'client_name' => null,
            'location' => null,
            'project_date' => null,
        ]);

        $this->get(route('projects.show', $incomplete->slug))
            ->assertOk()
            ->assertSee($incomplete->title)
            ->assertDontSee('Project Metrics has no metrics');

        $this->get(route('projects.show', $legacy->slug))
            ->assertOk()
            ->assertSee('Legacy production content', false)
            ->assertSee('Legacy production service')
            ->assertSee('Legacy field')
            ->assertSee('Legacy value');
    }

    private function projectTemplate(string $status = 'published'): Template
    {
        return Template::query()->create([
            'title' => 'Project runtime hardening',
            'slug' => 'project-runtime-hardening-'.uniqid(),
            'type' => 'project_single',
            'status' => $status,
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [
                ['type' => 'project_overview', 'data' => []],
                ['type' => 'project_metrics', 'data' => []],
                ['type' => 'project_services', 'data' => []],
                ['type' => 'project_gallery', 'data' => []],
                ['type' => 'related_projects', 'data' => []],
            ],
        ]);
    }

    private function addMedia(Project $project, string $fileName, string $collection): void
    {
        $project->media()->create([
            'collection_name' => $collection,
            'name' => pathinfo($fileName, PATHINFO_FILENAME),
            'file_name' => $fileName,
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 1,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
            'order_column' => 1,
        ]);
    }
}
