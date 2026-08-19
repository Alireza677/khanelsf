<?php

namespace Tests\Feature;

use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\ProjectArchiveTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectArchiveGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_installs_one_editable_default_gallery_template_without_overwriting_changes(): void
    {
        $this->seed(ProjectArchiveTemplateSeeder::class);
        $template = Template::query()->where('slug', ProjectArchiveTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();

        $this->assertSame('projects_index', $template->type);
        $this->assertSame(['template_archive_header', 'template_content_grid'], collect($template->blocks)->pluck('type')->all());
        $this->assertSame('masonry_gallery', data_get($template->blocks, '1.data.presentation_variant'));

        $blocks = $template->blocks;
        data_set($blocks, '0.data.title', 'عنوان ویرایش‌شده');
        $template->update(['blocks' => $blocks]);
        $this->seed(ProjectArchiveTemplateSeeder::class);

        $this->assertSame(1, Template::query()->where('slug', ProjectArchiveTemplateSeeder::TEMPLATE_SLUG)->count());
        $this->assertSame('عنوان ویرایش‌شده', data_get($template->fresh()->blocks, '0.data.title'));
    }

    public function test_archive_renders_masonry_overlay_real_fields_and_action_click_through(): void
    {
        $this->seed(ProjectArchiveTemplateSeeder::class);
        $category = ProjectCategory::factory()->create(['name' => 'معماری']);
        $project = Project::factory()->published()->for($category, 'category')->create([
            'title' => 'خانه آفتاب',
            'slug' => 'sun-house',
            'excerpt' => 'روایتی کوتاه از طراحی و اجرای پروژه',
            'location' => 'تهران',
            'project_type' => 'طراحی و اجرا',
            'project_date' => '2026-08-01',
        ]);

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertSee('shared-collection--masonry_gallery', false)
            ->assertSee('shared-collection-card--masonry', false)
            ->assertSee('خانه آفتاب')
            ->assertSee('معماری')
            ->assertSee('تهران')
            ->assertSee('طراحی و اجرا')
            ->assertSee('مشاهده پروژه')
            ->assertSee(route('projects.show', $project->slug, absolute: false), false);
    }

    public function test_empty_and_item_counts_keep_shared_collection_contract(): void
    {
        $this->seed(ProjectArchiveTemplateSeeder::class);
        $this->get(route('galleries.index'))->assertOk()->assertSee('shared-collection__empty', false);

        foreach ([1, 2, 3, 6, 12] as $count) {
            Project::query()->delete();
            Project::factory()->published()->count($count)->create();
            $html = $this->get(route('galleries.index'))->assertOk()->getContent();
            $this->assertSame($count, substr_count($html, 'shared-collection-card--masonry'));
        }
    }

    public function test_builder_can_switch_generic_presentation_and_preview_uses_real_collection(): void
    {
        $this->seed(ProjectArchiveTemplateSeeder::class);
        $template = Template::query()->where('slug', ProjectArchiveTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $keys = collect($component->get('data')['blocks'])->mapWithKeys(
            fn (array $block, string $uuid): array => [$block['type'] => $uuid],
        );

        $component
            ->set("data.blocks.{$keys['template_content_grid']}.data.presentation_variant", 'clean_grid')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = collect($template->fresh()->blocks)->keyBy('type');
        $this->assertSame('clean_grid', data_get($saved, 'template_content_grid.data.presentation_variant'));

        Project::factory()->published()->create(['title' => 'پروژه پیش‌نمایش']);
        $this->get(route('admin.preview.templates.show', $template))
            ->assertOk()
            ->assertSee('پروژه پیش‌نمایش');
    }
}
