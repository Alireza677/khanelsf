<?php

namespace Tests\Feature;

use App\CMS\Templates\TemplatePublicationValidator;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ServiceArchiveTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceArchiveTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_installs_one_published_default_with_existing_archive_blocks_only(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);
        $template = $this->archiveTemplate();

        $this->assertSame('service_index', $template->type);
        $this->assertSame('published', $template->status);
        $this->assertTrue($template->is_default);
        $this->assertSame(['type' => 'all'], $template->conditions);
        $this->assertSame(
            ['template_archive_header', 'template_content_grid'],
            collect($template->blocks)->pluck('type')->all(),
        );
        $this->assertSame([], app(TemplatePublicationValidator::class)->validate($template->toArray()));
        $this->assertSame('modern', data_get($template->blocks, '0.data.variant'));
        $this->assertSame(3, data_get($template->blocks, '1.data.columns_desktop'));
        $this->assertSame('16:10', data_get($template->blocks, '1.data.image_ratio'));
        $this->assertSame('مشاهده جزئیات', data_get($template->blocks, '1.data.action_label'));
    }

    public function test_reseeding_is_idempotent_and_preserves_editor_customization(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);
        $template = $this->archiveTemplate();
        $blocks = $template->blocks;
        data_set($blocks, '0.data.title', 'عنوان اختصاصی مدیر');
        data_set($blocks, '0.data.background_type', 'solid');
        data_set($blocks, '0.data.background_color', '#112233');
        data_set($blocks, '1.data.columns_desktop', 2);
        $template->update(['title' => 'نام اختصاصی قالب', 'blocks' => $blocks]);

        $this->seed(ServiceArchiveTemplateSeeder::class);
        $fresh = $template->fresh();

        $this->assertSame(1, Template::query()->where('slug', ServiceArchiveTemplateSeeder::TEMPLATE_SLUG)->count());
        $this->assertSame('نام اختصاصی قالب', $fresh->title);
        $this->assertSame('عنوان اختصاصی مدیر', data_get($fresh->blocks, '0.data.title'));
        $this->assertSame('#112233', data_get($fresh->blocks, '0.data.background_color'));
        $this->assertSame(2, data_get($fresh->blocks, '1.data.columns_desktop'));
    }

    public function test_seeded_template_renders_the_existing_polished_service_archive(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);
        $this->service('طراحی تجربه کاربری', 'ux-design', 'توضیح واقعی خدمت');

        $this->assertTrue(
            app(TemplateService::class)->findTemplateFor('service_index')?->is($this->archiveTemplate()),
        );

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('archive-header--modern', false)
            ->assertSee('خدمات حرفه‌ای برای رشد کسب‌وکار شما')
            ->assertSee('طراحی تجربه کاربری')
            ->assertSee('توضیح واقعی خدمت')
            ->assertSee('shared-collection__grid--3', false)
            ->assertSee('shared-collection--ratio-16-10', false)
            ->assertSee('مشاهده جزئیات')
            ->assertSee(route('services.show', 'ux-design', absolute: false), false);
    }

    public function test_template_builder_settings_save_reload_and_only_change_presentation(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
        $template = $this->archiveTemplate();
        $service = $this->service('خدمت قابل تنظیم', 'editable-service', 'این توضیح باید فقط پنهان شود.');

        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $keys = collect($component->get('data')['blocks'])->mapWithKeys(
            fn (array $block, string $uuid): array => [$block['type'] => $uuid],
        );

        $component
            ->set("data.blocks.{$keys['template_archive_header']}.data.title", 'عنوان ویرایش‌شده آرشیو')
            ->set("data.blocks.{$keys['template_archive_header']}.data.description", 'توضیح ویرایش‌شده آرشیو')
            ->set("data.blocks.{$keys['template_archive_header']}.data.background_type", 'solid')
            ->set("data.blocks.{$keys['template_archive_header']}.data.background_color", '#ddeeff')
            ->set("data.blocks.{$keys['template_archive_header']}.data.alignment", 'start')
            ->set("data.blocks.{$keys['template_content_grid']}.data.columns_desktop", 2)
            ->set("data.blocks.{$keys['template_content_grid']}.data.show_excerpt", false)
            ->set("data.blocks.{$keys['template_content_grid']}.data.action_label", 'بررسی خدمت')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = collect($template->fresh()->blocks)->keyBy('type');
        $this->assertSame('عنوان ویرایش‌شده آرشیو', data_get($saved, 'template_archive_header.data.title'));
        $this->assertSame('#ddeeff', data_get($saved, 'template_archive_header.data.background_color'));
        $this->assertSame(2, data_get($saved, 'template_content_grid.data.columns_desktop'));
        $this->assertFalse(data_get($saved, 'template_content_grid.data.show_excerpt'));

        $reloaded = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $reloadedBlocks = collect($reloaded->get('data')['blocks'])->keyBy('type');
        $this->assertSame('عنوان ویرایش‌شده آرشیو', data_get($reloadedBlocks, 'template_archive_header.data.title'));
        $this->assertSame('بررسی خدمت', data_get($reloadedBlocks, 'template_content_grid.data.action_label'));

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('عنوان ویرایش‌شده آرشیو')
            ->assertSee('توضیح ویرایش‌شده آرشیو')
            ->assertSee('background-color: #ddeeff', false)
            ->assertSee('archive-header--align-start', false)
            ->assertSee('shared-collection__grid--2', false)
            ->assertSee('بررسی خدمت')
            ->assertDontSee('این توضیح باید فقط پنهان شود.');

        $this->assertSame('این توضیح باید فقط پنهان شود.', $service->fresh()->excerpt);
    }

    public function test_draft_or_missing_template_uses_the_production_safe_shared_collection_fallback(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);
        $this->archiveTemplate()->update(['status' => 'draft']);
        $this->service('خدمت مسیر جایگزین', 'fallback-service', 'Fallback excerpt');

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('class="services-archive"', false)
            ->assertSee('خدمات حرفه‌ای برای رشد کسب‌وکار شما')
            ->assertSee('خدمت مسیر جایگزین')
            ->assertSee('Fallback excerpt')
            ->assertDontSee('archive-header--modern', false);
    }

    public function test_old_archive_template_without_new_settings_remains_renderable(): void
    {
        Template::query()->create([
            'title' => 'قالب قدیمی آرشیو خدمات',
            'slug' => 'legacy-service-archive',
            'type' => 'service_index',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [
                ['type' => 'template_archive_header', 'data' => []],
                ['type' => 'template_content_grid', 'data' => []],
            ],
        ]);
        $this->service('خدمت قالب قدیمی', 'legacy-template-service', 'توضیح قالب قدیمی');

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('خدمات حرفه‌ای برای رشد کسب‌وکار شما')
            ->assertSee('خدمت قالب قدیمی')
            ->assertSee('توضیح قالب قدیمی')
            ->assertSee('مشاهده خدمت');
    }

    public function test_admin_preview_uses_the_same_canonical_collection_branch(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);
        $this->service('خدمت پیش‌نمایش آرشیو', 'archive-preview-service', 'Preview excerpt');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.templates.show', $this->archiveTemplate()))
            ->assertOk()
            ->assertSee('خدمت پیش‌نمایش آرشیو')
            ->assertSee('class="shared-collection-card"', false)
            ->assertSee('content="noindex, nofollow"', false);
    }

    public function test_fresh_database_seed_installs_an_editable_archive_and_services_route_is_ready(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('templates', [
            'slug' => ServiceArchiveTemplateSeeder::TEMPLATE_SLUG,
            'type' => 'service_index',
            'status' => 'published',
            'is_default' => true,
        ]);

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('archive-header--modern', false)
            ->assertSee('template-content-grid', false);
    }

    private function archiveTemplate(): Template
    {
        return Template::query()->where('slug', ServiceArchiveTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();
    }

    private function service(string $name, string $slug, ?string $excerpt = null): Service
    {
        return Service::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => Service::STATUS_PUBLISHED,
            'excerpt' => $excerpt,
            'icon' => 'icon-activity',
        ]);
    }
}
