<?php

namespace Tests\Feature;

use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Project;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\PersianProjectDetailDemoSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StandardProjectTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
        $this->assertTrue($template->is_default);
        $this->assertSame(['type' => 'all'], $template->conditions);
        $this->assertSame([
            'project_header',
            'project_overview',
            'project_story',
            'project_metrics',
            'project_gallery',
            'project_services',
            'related_projects',
            'cta',
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

    public function test_fresh_database_seed_selects_the_standard_template_for_project_single(): void
    {
        $this->seed(DatabaseSeeder::class);
        $template = Template::query()->where('slug', StandardProjectTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();
        $project = Project::factory()->published()->create(['title' => 'پروژه نصب تازه']);

        $this->assertTrue($template->is_default);
        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee('project-case-study', false)
            ->assertSee('shared-hero', false)
            ->assertSee('پروژه نصب تازه');
    }

    public function test_template_builder_saves_project_header_canonical_actions(): void
    {
        $this->seed(StandardProjectTemplateSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
        $template = Template::query()->where('slug', StandardProjectTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();
        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $key = collect($component->get('data')['blocks'])->search(fn (array $block): bool => $block['type'] === 'project_header');

        $component
            ->set("data.blocks.{$key}.data.settings.primary_action.label", 'برآورد پروژه')
            ->set("data.blocks.{$key}.data.settings.primary_action.action.type", 'custom_url')
            ->set("data.blocks.{$key}.data.settings.primary_action.action.value", '/estimate')
            ->set("data.blocks.{$key}.data.settings.secondary_action.label", 'تماس مستقیم')
            ->set("data.blocks.{$key}.data.settings.secondary_action.action.type", 'phone')
            ->set("data.blocks.{$key}.data.settings.secondary_action.action.value", '۰۹۱۲۳۴۵۶۷۸۹')
            ->call('save')
            ->assertHasNoFormErrors();

        $fresh = $template->fresh();
        $header = collect($fresh->blocks)->firstWhere('type', 'project_header');
        $this->assertSame('custom_url', data_get($header, 'data.settings.primary_action.action.type'));
        $this->assertSame('/estimate', data_get($header, 'data.settings.primary_action.action.value'));
        $this->assertSame('phone', data_get($header, 'data.settings.secondary_action.action.type'));
        $this->assertSame('09123456789', data_get($header, 'data.settings.secondary_action.action.value'));
    }
}
