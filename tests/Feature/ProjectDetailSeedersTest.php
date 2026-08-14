<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Service;
use App\Models\Template;
use Database\Seeders\PersianProjectDetailDemoSeeder;
use Database\Seeders\StandardProjectTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDetailSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_seeder_installs_only_the_industry_neutral_project_template(): void
    {
        $this->seed(StandardProjectTemplateSeeder::class);

        $template = Template::query()->where('slug', StandardProjectTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();

        $this->assertSame('قالب استاندارد نمایش پروژه', $template->title);
        $this->assertSame('published', $template->status);
        $this->assertSame('project_single', $template->type);
        $this->assertFalse($template->is_default);
        $this->assertSame(['type' => 'all'], $template->conditions);
        $this->assertSame([
            'project_header',
            'project_overview',
            'project_metrics',
            'project_story',
            'project_services',
            'project_gallery',
            'cta',
            'related_projects',
        ], collect($template->blocks)->pluck('type')->all());
        $this->assertCount(8, collect($template->blocks)->pluck('data.block_id')->filter()->unique());
        $this->assertSame(0, Project::query()->count());

        $payload = json_encode($template->blocks, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('LSF', $payload);
        $this->assertStringNotContainsString('کرمان', $payload);
        $this->assertStringNotContainsString('ویلا', $payload);
    }

    public function test_core_seeder_is_idempotent_and_preserves_admin_edits(): void
    {
        $this->seed(StandardProjectTemplateSeeder::class);

        $template = Template::query()->where('slug', StandardProjectTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();
        $template->update(['title' => 'قالب ویرایش‌شده مدیر']);
        $blockIds = collect($template->blocks)->pluck('data.block_id')->all();

        $this->seed(StandardProjectTemplateSeeder::class);

        $this->assertSame(1, Template::query()->where('slug', StandardProjectTemplateSeeder::TEMPLATE_SLUG)->count());
        $this->assertSame('قالب ویرایش‌شده مدیر', $template->fresh()->title);
        $this->assertSame($blockIds, collect($template->fresh()->blocks)->pluck('data.block_id')->all());
        $this->assertSame(0, Project::query()->count());
    }

    public function test_demo_project_is_created_only_when_its_seeder_is_run_explicitly(): void
    {
        $this->seed(StandardProjectTemplateSeeder::class);
        $this->assertDatabaseMissing('projects', ['slug' => PersianProjectDetailDemoSeeder::PROJECT_SLUG]);

        $service = Service::query()->create([
            'name' => 'طراحی سازه LSF',
            'slug' => 'lsf-structural-design',
            'status' => 'active',
        ]);

        $this->seed(PersianProjectDetailDemoSeeder::class);
        $this->seed(PersianProjectDetailDemoSeeder::class);

        $project = Project::query()->where('slug', PersianProjectDetailDemoSeeder::PROJECT_SLUG)->firstOrFail();

        $this->assertSame('ساخت ویلای مدرن با سازه LSF در کرمان', $project->title);
        $this->assertCount(4, $project->metrics);
        $this->assertTrue($project->relatedServices->contains($service));
        $this->assertSame(1, Project::query()->where('slug', PersianProjectDetailDemoSeeder::PROJECT_SLUG)->count());
        $this->assertSame(1, $project->relatedServices()->count());
        $this->assertSame('ساخت ویلای LSF در کرمان | پروژه اجرای سازه سبک فولادی', $project->seo_title);
    }
}
