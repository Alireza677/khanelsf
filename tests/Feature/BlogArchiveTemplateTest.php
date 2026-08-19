<?php

namespace Tests\Feature;

use App\CMS\Templates\TemplatePublicationValidator;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Category;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateService;
use Database\Seeders\BlogArchiveTemplateSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogArchiveTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_installs_one_published_default_using_existing_archive_blocks(): void
    {
        $this->seed(BlogArchiveTemplateSeeder::class);
        $template = $this->archiveTemplate();

        $this->assertSame('blog_index', $template->type);
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
        $this->assertFalse(data_get($template->blocks, '1.data.show_icon'));
        $this->assertSame('مطالعه مقاله', data_get($template->blocks, '1.data.action_label'));
    }

    public function test_reseed_does_not_duplicate_or_overwrite_editor_customization(): void
    {
        $this->seed(BlogArchiveTemplateSeeder::class);
        $template = $this->archiveTemplate();
        $blocks = $template->blocks;
        data_set($blocks, '0.data.title', 'عنوان سفارشی وبلاگ');
        data_set($blocks, '0.data.background_type', 'solid');
        data_set($blocks, '1.data.columns_desktop', 2);
        $template->update(['title' => 'قالب ویرایش‌شده مدیر', 'blocks' => $blocks]);

        $this->seed(BlogArchiveTemplateSeeder::class);
        $fresh = $template->fresh();

        $this->assertSame(1, Template::query()->where('slug', BlogArchiveTemplateSeeder::TEMPLATE_SLUG)->count());
        $this->assertSame('قالب ویرایش‌شده مدیر', $fresh->title);
        $this->assertSame('عنوان سفارشی وبلاگ', data_get($fresh->blocks, '0.data.title'));
        $this->assertSame('solid', data_get($fresh->blocks, '0.data.background_type'));
        $this->assertSame(2, data_get($fresh->blocks, '1.data.columns_desktop'));
    }

    public function test_seeded_template_renders_canonical_blog_cards_with_badge_meta_and_action(): void
    {
        $this->seed(BlogArchiveTemplateSeeder::class);
        $category = Category::factory()->create(['title' => 'رشد دیجیتال']);
        $post = Post::factory()->published()->for($category)->create([
            'title' => 'راهنمای رشد پایدار',
            'slug' => 'sustainable-growth-guide',
            'excerpt' => 'خلاصه کاربردی مقاله برای مدیران کسب‌وکار.',
            'published_at' => now()->subDays(2),
        ]);

        $this->assertTrue(app(TemplateService::class)->findTemplateFor('blog_index')?->is($this->archiveTemplate()));

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('archive-header--modern', false)
            ->assertSee('دانش، تجربه و ایده‌هایی برای رشد بهتر')
            ->assertSee('راهنمای رشد پایدار')
            ->assertSee('رشد دیجیتال')
            ->assertSee('تاریخ انتشار')
            ->assertSee($post->published_at->toFormattedDateString())
            ->assertSee('مطالعه مقاله')
            ->assertSee(route('blog.show', $post->slug, absolute: false), false);
    }

    public function test_builder_settings_save_reload_render_and_do_not_mutate_post_data(): void
    {
        $this->seed(BlogArchiveTemplateSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
        $template = $this->archiveTemplate();
        $post = Post::factory()->published()->create([
            'title' => 'مقاله قابل تنظیم',
            'excerpt' => 'این خلاصه فقط باید از نمایش پنهان شود.',
        ]);

        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $keys = collect($component->get('data')['blocks'])->mapWithKeys(
            fn (array $block, string $uuid): array => [$block['type'] => $uuid],
        );

        $component
            ->set("data.blocks.{$keys['template_archive_header']}.data.title", 'مجله تخصصی ما')
            ->set("data.blocks.{$keys['template_archive_header']}.data.description", 'توضیح ویرایش‌شده مجله')
            ->set("data.blocks.{$keys['template_archive_header']}.data.background_type", 'solid')
            ->set("data.blocks.{$keys['template_archive_header']}.data.background_color", '#f1f5f9')
            ->set("data.blocks.{$keys['template_content_grid']}.data.columns_desktop", 2)
            ->set("data.blocks.{$keys['template_content_grid']}.data.show_excerpt", false)
            ->set("data.blocks.{$keys['template_content_grid']}.data.action_label", 'ادامه مطلب')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = collect($template->fresh()->blocks)->keyBy('type');
        $this->assertSame('مجله تخصصی ما', data_get($saved, 'template_archive_header.data.title'));
        $this->assertSame('#f1f5f9', data_get($saved, 'template_archive_header.data.background_color'));
        $this->assertSame(2, data_get($saved, 'template_content_grid.data.columns_desktop'));
        $this->assertFalse(data_get($saved, 'template_content_grid.data.show_excerpt'));

        $reloaded = collect(
            Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->get('data')['blocks'],
        )->keyBy('type');
        $this->assertSame('مجله تخصصی ما', data_get($reloaded, 'template_archive_header.data.title'));
        $this->assertSame('ادامه مطلب', data_get($reloaded, 'template_content_grid.data.action_label'));

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('مجله تخصصی ما')
            ->assertSee('background-color: #f1f5f9', false)
            ->assertSee('shared-collection__grid--2', false)
            ->assertSee('ادامه مطلب')
            ->assertDontSee('این خلاصه فقط باید از نمایش پنهان شود.');

        $this->assertSame('این خلاصه فقط باید از نمایش پنهان شود.', $post->fresh()->excerpt);
    }

    public function test_draft_or_missing_template_uses_existing_shared_collection_fallback(): void
    {
        $this->seed(BlogArchiveTemplateSeeder::class);
        $this->archiveTemplate()->update(['status' => 'draft']);
        Post::factory()->published()->create(['title' => 'مقاله مسیر جایگزین']);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('مقاله مسیر جایگزین')
            ->assertSee('class="shared-collection-card"', false)
            ->assertDontSee('archive-header--modern', false);
    }

    public function test_old_blog_template_without_new_settings_remains_compatible(): void
    {
        Template::query()->create([
            'title' => 'قالب قدیمی وبلاگ',
            'slug' => 'legacy-blog-index',
            'type' => 'blog_index',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [
                ['type' => 'template_archive_header', 'data' => []],
                ['type' => 'template_content_grid', 'data' => []],
            ],
        ]);
        Post::factory()->published()->create([
            'title' => 'مقاله قالب قدیمی',
            'excerpt' => 'خلاصه قالب قدیمی',
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('مقاله قالب قدیمی')
            ->assertSee('خلاصه قالب قدیمی')
            ->assertSee('مشاهده نوشته');
    }

    public function test_admin_preview_uses_real_adapter_and_handles_an_empty_collection(): void
    {
        $this->seed(BlogArchiveTemplateSeeder::class);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.templates.show', $this->archiveTemplate()))
            ->assertOk()
            ->assertSee('هنوز نوشته‌ای منتشر نشده است.')
            ->assertSee('shared-collection__empty', false)
            ->assertSee('content="noindex, nofollow"', false);
    }

    public function test_fresh_database_seed_installs_blog_template_and_blog_is_ready(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('templates', [
            'slug' => BlogArchiveTemplateSeeder::TEMPLATE_SLUG,
            'type' => 'blog_index',
            'status' => 'published',
            'is_default' => true,
        ]);

        $this->assertSame(1, Template::query()->where('type', 'blog_index')->where('is_default', true)->count());

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('archive-header--modern', false)
            ->assertSee('template-content-grid', false);
    }

    private function archiveTemplate(): Template
    {
        return Template::query()->where('slug', BlogArchiveTemplateSeeder::TEMPLATE_SLUG)->firstOrFail();
    }
}
