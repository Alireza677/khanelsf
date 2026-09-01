<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\SiteFooter\SiteFooterBlock;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\TemplateResource;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\CorporateFooterTemplateSeeder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ViewField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SiteFooterTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_ID = '01JFOOTERTEST0000000000000';

    public function test_selector_and_editor_use_the_existing_template_and_shared_media_foundations(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());
        $published = $this->template();
        $draft = $this->template('draft-footer', 'draft');
        $otherTarget = Template::query()->create([
            'title' => 'Page template', 'slug' => 'page-template', 'type' => 'page',
            'status' => 'published', 'blocks' => [], 'conditions' => ['type' => 'all'],
        ]);
        $asset = $this->media('badge.png');
        $component = Livewire::test(ManageSiteSettings::class);
        $selector = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Select && $field->getName() === 'footer_template_id');

        $this->assertInstanceOf(Select::class, $selector);
        $this->assertSame([$published->getKey() => $published->title], $selector->getOptions());
        $this->assertArrayNotHasKey($draft->getKey(), $selector->getOptions());
        $this->assertArrayNotHasKey($otherTarget->getKey(), $selector->getOptions());

        $block = app(BlockRegistry::class)->find('site_footer');
        $definitions = $this->invokeBlockDefinitions('site_footer');
        $schema = collect($block->filamentSchema(HeroBlock::CONTEXT_TEMPLATE));
        $badgeRepeater = $schema->first(fn ($field): bool => $field instanceof Repeater && str_ends_with($field->getName(), 'badges'));
        $badgePicker = collect($badgeRepeater->getChildComponents())
            ->first(fn ($field): bool => $field instanceof ViewField && $field->getName() === 'media_id');

        $this->assertInstanceOf(SiteFooterBlock::class, $block);
        $this->assertCount(1, $definitions);
        $this->assertSame('site_footer', $definitions[0]->getName());
        $this->assertSame('filament.forms.components.media-library-picker', $badgePicker->getView());
        $this->assertSame($asset->getKey(), SiteFooterBlock::mediaLibraryImageItems()[0]['id']);
        $this->assertCount(2, $schema->filter(fn ($field): bool => $field instanceof ActionPicker));

        $component
            ->set('data.site_name', 'سایت آزمایشی')
            ->set('data.footer_template_id', $published->getKey())
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'footer_template_id',
            'value' => (string) $published->getKey(),
            'group' => 'footer',
            'type' => 'select',
        ]);
    }

    public function test_selected_footer_renders_three_menus_site_contacts_about_and_structured_legal_links(): void
    {
        $this->home();
        $legalPage = Page::factory()->published()->create(['slug' => 'privacy', 'title' => 'حریم خصوصی']);
        $menus = [
            $this->menu('درباره شرکت', 'درباره ما', '/about'),
            $this->menu('خدمات', 'خدمات مهندسی', '/services'),
            $this->menu('دسترسی سریع', 'وبلاگ', '/blog'),
        ];
        $this->settings([
            'site_name' => 'سازه ایرانی',
            'contact_phone' => '021 1234 5678',
            'contact_mobile' => '0912 123 4567',
            'contact_email' => 'info@example.test',
            'contact_address' => 'تهران، خیابان نمونه',
            'working_hours' => 'شنبه تا چهارشنبه، ۸ تا ۱۷',
            'footer_text' => 'متن معرفی پویا از تنظیمات سایت',
        ]);
        $template = $this->template(blocks: $this->blocks($menus, null, $legalPage));
        $this->select($template);
        $stored = json_encode($template->blocks, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('"reference_id":'.$legalPage->getKey(), $stored);
        $this->assertStringNotContainsString('/privacy', $stored);

        $response = $this->get(route('home'));
        $response
            ->assertOk()
            ->assertSee('corporate-footer', false)
            ->assertSee('درباره شرکت')
            ->assertSee('خدمات')
            ->assertSee('دسترسی سریع')
            ->assertSee('درباره ما')
            ->assertSee('خدمات مهندسی')
            ->assertSee('وبلاگ')
            ->assertSee('tel:02112345678', false)
            ->assertSee('tel:09121234567', false)
            ->assertSee('mailto:info@example.test', false)
            ->assertSee('تهران، خیابان نمونه')
            ->assertSee('شنبه تا چهارشنبه، ۸ تا ۱۷')
            ->assertSee('متن معرفی پویا از تنظیمات سایت')
            ->assertSee($legalPage->resolveNavigationUrl(), false)
            ->assertSee((string) now()->year)
            ->assertSee('سازه ایرانی');
    }

    public function test_badge_reuses_in_use_central_media_by_id_and_survives_metadata_changes_without_copies(): void
    {
        Storage::fake('public');
        $this->home();
        $asset = $this->media('trust.png');
        $page = Page::factory()->create();
        PageResource::syncFeaturedImage($page, $asset->getKey());
        $files = Storage::disk('public')->allFiles();
        $template = $this->template(blocks: $this->blocks([], $asset));
        $this->select($template);
        $stored = json_encode($template->blocks, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"media_id":'.$asset->getKey(), $stored);
        $this->assertStringNotContainsString($asset->getUrl(), $stored);
        $this->assertCount(1, Media::all());
        $this->assertDatabaseCount('media_usages', 1);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($asset->getUrl(), false)
            ->assertSee('نماد مرکزی');

        $asset->name = 'عنوان تازه رسانه';
        $asset->setCustomProperty('alt_text', 'توضیح جایگزین تازه');
        $asset->save();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee($asset->fresh()->getUrl(), false)
            ->assertSee('توضیح جایگزین تازه');

        $this->assertSame($asset->getKey(), data_get($template->fresh()->blocks, '0.data.content.badges.0.media_id'));
        $this->assertCount(1, Media::all());
        $this->assertDatabaseCount('media_usages', 1);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_missing_invalid_and_disabled_data_fail_closed_without_placeholders(): void
    {
        $this->home();
        $template = $this->template(blocks: $this->blocks([], null, null, [
            ['title' => 'نامعتبر', 'media_id' => 999999, 'url' => null, 'alt' => null, 'sort_order' => 1, 'enabled' => true],
            ['title' => 'غیرفعال', 'media_id' => 888888, 'url' => null, 'alt' => null, 'sort_order' => 2, 'enabled' => false],
        ]));
        $this->select($template);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('corporate-footer', false)
            ->assertDontSee('نامعتبر')
            ->assertDontSee('غیرفعال')
            ->assertDontSee('corporate-footer__badge', false)
            ->assertDontSee('corporate-footer__contact', false);
    }

    public function test_existing_default_footer_resolution_is_preserved_when_no_selector_setting_exists(): void
    {
        $this->home();
        $template = $this->template(blocks: $this->blocks());
        $template->update(['is_default' => true]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('corporate-footer', false);
    }

    public function test_draft_preview_seed_and_scoped_responsive_contract_are_available(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $draft = $this->template('draft-corporate-footer', 'draft', $this->blocks());

        $this->get(route('admin.preview.templates.show', $draft))
            ->assertOk()
            ->assertSee('فوتر در پایین')
            ->assertSee('corporate-footer', false);

        $this->seed(CorporateFooterTemplateSeeder::class);
        $seeded = Template::query()->where('slug', 'corporate-dark-footer-v1')->sole();
        $this->assertSame('site_footer', $seeded->type);
        $this->assertSame('published', $seeded->status);
        $this->assertDatabaseHas('settings', [
            'key' => 'footer_template_id',
            'value' => (string) $seeded->getKey(),
        ]);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('.site-footer.corporate-footer', $css);
        $this->assertStringContainsString('grid-template-columns: repeat(3, minmax(0, 1fr)) minmax(15rem, 1.25fr)', $css);
        $this->assertStringContainsString('@media (max-width: 1024px)', $css);
        $this->assertStringContainsString('@media (max-width: 640px)', $css);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr)', $css);
        $this->assertStringNotContainsString('.corporate-footer !important', $css);
    }

    private function home(): Page
    {
        return Page::factory()->published()->create(['slug' => 'home', 'title' => 'خانه', 'blocks' => []]);
    }

    /** @param array<string, string> $values */
    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => str_starts_with($key, 'contact_') || $key === 'working_hours' ? 'contact' : 'general', 'type' => 'text'],
            );
        }
    }

    private function template(string $slug = 'corporate-dark-footer-v1', string $status = 'published', array $blocks = []): Template
    {
        return Template::query()->create([
            'title' => $slug === 'corporate-dark-footer-v1' ? 'فوتر سازمانی تیره' : $slug,
            'slug' => $slug,
            'type' => 'site_footer',
            'status' => $status,
            'is_default' => false,
            'conditions' => ['type' => 'all'],
            'blocks' => $blocks,
        ]);
    }

    private function select(Template $template): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'footer_template_id'],
            ['value' => (string) $template->getKey(), 'group' => 'footer', 'type' => 'select'],
        );
    }

    private function menu(string $title, string $itemTitle, string $url): Menu
    {
        $menu = Menu::query()->create([
            'title' => $title,
            'slug' => str($title)->slug().'-'.str()->random(5),
            'location' => null,
            'status' => 'active',
        ]);
        MenuItem::query()->create([
            'menu_id' => $menu->getKey(),
            'title' => $itemTitle,
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => $url,
            'target' => '_self',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        return $menu;
    }

    private function media(string $filename): Media
    {
        return User::factory()->create()
            ->addMedia(UploadedFile::fake()->image($filename, 200, 200))
            ->toMediaCollection('media_library', 'public');
    }

    /**
     * @param  array<int, Menu>  $menus
     * @param  array<int, array<string, mixed>>|null  $badges
     */
    private function blocks(array $menus = [], ?Media $media = null, ?Page $legalPage = null, ?array $badges = null): array
    {
        $action = $legalPage ? [
            'schema_version' => 1,
            'type' => 'page',
            'reference_id' => $legalPage->getKey(),
            'open_in_new_tab' => false,
        ] : null;

        return [[
            'type' => 'site_footer',
            'data' => [
                'block_id' => self::BLOCK_ID,
                'schema_version' => 1,
                'template' => 'corporate-dark-v1',
                'content' => [
                    'menu_columns' => collect($menus)->map(fn (Menu $menu): array => [
                        'title' => $menu->title,
                        'menu_id' => $menu->getKey(),
                    ])->all(),
                    'contact_title' => 'ارتباط با ما',
                    'about_title' => 'درباره ما',
                    'about_text' => null,
                    'badges_title' => 'مجوزها و نمادهای اعتماد',
                    'badges' => $badges ?? ($media ? [[
                        'title' => 'نماد مرکزی',
                        'media_id' => $media->getKey(),
                        'url' => null,
                        'alt' => null,
                        'sort_order' => 0,
                        'enabled' => true,
                    ]] : []),
                    'privacy_label' => 'حریم خصوصی',
                    'privacy_action' => $action,
                    'terms_label' => 'قوانین و مقررات',
                    'terms_action' => null,
                ],
            ],
        ]];
    }

    private function invokeBlockDefinitions(string $target): array
    {
        $method = new \ReflectionMethod(TemplateResource::class, 'blockDefinitions');

        return $method->invoke(null, $target);
    }
}
