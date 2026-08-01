<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\SiteHeader\SiteHeaderBlock;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\TemplateResource;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteHeaderTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_ID = '01JHEADER00000000000000000';

    public function test_selector_lists_only_published_site_header_templates(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $published = $this->template(status: 'published');
        $draft = $this->template('draft-header', 'site_header', 'draft');
        $otherTarget = $this->template('published-page', 'page', 'published');
        $component = Livewire::test(ManageSiteSettings::class);
        $selector = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Select
                && $field->getName() === 'header_template_id');

        $this->assertInstanceOf(Select::class, $selector);
        $this->assertSame(
            [$published->getKey() => $published->title],
            $selector->getOptions(),
        );
        $this->assertArrayNotHasKey($draft->getKey(), $selector->getOptions());
        $this->assertArrayNotHasKey($otherTarget->getKey(), $selector->getOptions());

        $component
            ->set('data.site_name', 'سازه ایرانی')
            ->set('data.header_template_id', $published->getKey())
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'header_template_id',
            'value' => (string) $published->getKey(),
            'group' => 'header',
            'type' => 'select',
        ]);
    }

    public function test_registered_header_block_is_the_only_site_header_editor_definition(): void
    {
        $block = app(BlockRegistry::class)->find('site_header');
        $definitions = $this->invokeBlockDefinitions('site_header');
        $pickers = collect($block->filamentSchema(HeroBlock::CONTEXT_TEMPLATE))
            ->filter(fn ($component): bool => $component instanceof ActionPicker);

        $this->assertInstanceOf(SiteHeaderBlock::class, $block);
        $this->assertCount(1, $definitions);
        $this->assertSame('site_header', $definitions[0]->getName());
        $this->assertCount(3, $pickers);

        foreach ($pickers as $picker) {
            $this->assertSame([
                'custom_url',
                'page',
                'project',
                'product',
                'service',
                'form',
                'anchor',
                'email',
                'phone',
            ], array_keys($picker->getTypeOptions()));
        }
    }

    public function test_selected_template_renders_navigation_and_runtime_resolved_actions(): void
    {
        $this->home();
        $target = Page::factory()->published()->create([
            'slug' => 'construction-estimate',
            'title' => 'برآورد ساخت',
        ]);
        $menu = $this->menu();
        $template = $this->template(
            blocks: $this->headerBlocks($target->getKey(), $menu->getKey()),
        );
        $this->select($template);
        $stored = json_encode($template->blocks, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('/construction-estimate', $stored);
        $this->assertStringContainsString('"reference_id":'.$target->getKey(), $stored);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('industrial-header', false)
            ->assertDontSee('industrial-header__topbar', false)
            ->assertDontSee('راهکارهای تخصصی ساخت‌وساز')
            ->assertSee('خدمات و پشتیبانی')
            ->assertSee('همکاری با ما')
            ->assertSee('محاسبه هزینه ساخت')
            ->assertSee('data-desktop-navigation', false)
            ->assertSee('data-navigation-more', false)
            ->assertSee('data-navigation-more-trigger', false)
            ->assertSee('بیشتر')
            ->assertSee($target->resolveNavigationUrl(), false)
            ->assertSee('پروژه‌ها')
            ->assertSee('/projects', false)
            ->assertSee(route('blog.search', absolute: false), false);
    }

    public function test_industrial_header_visual_contract_keeps_desktop_navigation_single_line(): void
    {
        $blade = file_get_contents(resource_path('views/partials/blocks/site-header-industrial.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('industrial-header__topbar', $blade);
        $this->assertStringNotContainsString('راهکارهای تخصصی ساخت‌وساز', $blade);
        $this->assertStringContainsString('flex-wrap: nowrap;', $css);
        $this->assertStringContainsString('@media (max-width: 1080px) and (min-width: 901px)', $css);
        $this->assertStringContainsString('navigation.scrollWidth > navigation.clientWidth', $javascript);
        $this->assertStringContainsString('moreItems.prepend', $javascript);
        $this->assertStringContainsString('window.innerWidth <= 900', $javascript);
        $this->assertStringContainsString("event.key === 'Escape'", $javascript);
        $this->assertStringContainsString('moreTrigger.focus()', $javascript);
    }

    public function test_industrial_header_actions_use_the_canonical_theme_palette(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.industrial-header__top-action\s*\{[^}]*background:\s*var\(--theme-secondary\);[^}]*color:\s*var\(--theme-background\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__top-actions\s*>\s*:first-child[^}]*\{\s*background:\s*var\(--theme-primary\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__primary-action\s*\{[^}]*background:\s*var\(--theme-primary\);[^}]*color:\s*var\(--theme-background\);/s',
            $css,
        );
        $this->assertStringContainsString('background: var(--theme-primary-hover);', $css);
        $this->assertStringContainsString('outline: 3px solid var(--theme-link);', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.industrial-header__(?:top-action|primary-action)[^{]*\{[^}]*(?:#b91c1c|#991b1b)/s',
            $css,
        );
    }

    public function test_industrial_header_mobile_drawer_and_sticky_actions_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('.industrial-header.is-top-actions-hidden', $css);
        $this->assertStringContainsString('height: calc(100dvh - 70px);', $css);
        $this->assertStringContainsString('position: fixed;', $css);
        $this->assertStringContainsString('body.industrial-mobile-menu-open', $css);
        $this->assertStringContainsString("header.classList.add('is-top-actions-hidden')", $javascript);
        $this->assertStringContainsString("header.classList.remove('is-top-actions-hidden')", $javascript);
        $this->assertStringContainsString("document.body.classList.add('industrial-mobile-menu-open')", $javascript);
        $this->assertStringContainsString("document.body.classList.remove('industrial-mobile-menu-open')", $javascript);
        $this->assertStringContainsString("toggle.setAttribute('aria-expanded', 'true')", $javascript);
        $this->assertStringContainsString('toggle.focus()', $javascript);
    }

    public function test_missing_draft_and_unavailable_actions_fail_closed_to_legacy_header(): void
    {
        $this->home();
        $draftTarget = Page::factory()->draft()->create();
        $draftTemplate = $this->template(
            status: 'draft',
            blocks: $this->headerBlocks($draftTarget->getKey()),
        );
        $this->select($draftTemplate);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-site-header', false)
            ->assertDontSee('industrial-header', false)
            ->assertDontSee('محاسبه هزینه ساخت');

        $publishedTemplate = $this->template(
            'published-unavailable-action',
            blocks: $this->headerBlocks($draftTarget->getKey()),
        );
        $this->select($publishedTemplate);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('industrial-header', false)
            ->assertDontSee('محاسبه هزینه ساخت')
            ->assertSee('سایت آزمایشی');

        Setting::query()->where('key', 'header_template_id')->update(['value' => '999999']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('industrial-header', false)
            ->assertSee('data-site-header', false);
    }

    public function test_no_selection_keeps_legacy_header_even_when_a_default_template_exists(): void
    {
        $this->home();
        $this->template(
            blocks: $this->headerBlocks(),
            isDefault: true,
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-site-header', false)
            ->assertDontSee('industrial-header', false)
            ->assertSee('تماس با ما');
    }

    public function test_navigation_sources_remain_fail_closed_and_mobile_controls_are_unique(): void
    {
        $this->home();
        $target = Page::factory()->published()->create();
        $menu = $this->menu(includeUnavailableSource: true);
        $template = $this->template(
            blocks: $this->headerBlocks($target->getKey(), $menu->getKey()),
        );
        $this->select($template);
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('پروژه‌ها', $html);
        $this->assertStringNotContainsString('منبع ناموجود', $html);
        $this->assertSame(1, substr_count($html, 'id="industrial-navigation-'));
        $this->assertSame(1, substr_count($html, 'aria-controls="industrial-navigation-'));
        $this->assertSame(1, substr_count($html, 'data-menu-toggle'));
        $this->assertSame(1, substr_count($html, 'data-site-nav'));
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-label="باز کردن منوی اصلی"', $html);
    }

    public function test_draft_header_template_preview_uses_the_same_header_contract_once(): void
    {
        $this->home();
        $target = Page::factory()->published()->create();
        $template = $this->template(
            'preview-industrial-header',
            status: 'draft',
            blocks: $this->headerBlocks($target->getKey()),
        );

        $html = $this
            ->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.templates.show', $template))
            ->assertOk()
            ->assertSee('پیش‌نمایش هدر در بالای همین صفحه نمایش داده شده است.')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="site-header industrial-header'));
        $this->assertStringContainsString('محاسبه هزینه ساخت', $html);
    }

    public function test_database_seed_provides_the_first_published_selected_header(): void
    {
        $this->seed();
        $template = Template::query()
            ->where('slug', 'industrial-header-v1')
            ->sole();
        $setting = Setting::query()
            ->where('key', 'header_template_id')
            ->sole();
        $stored = json_encode($template->blocks, JSON_THROW_ON_ERROR);

        $this->assertSame('هدر صنعتی دو سطحی', $template->title);
        $this->assertSame('site_header', $template->type);
        $this->assertSame('published', $template->status);
        $this->assertSame((string) $template->getKey(), $setting->value);
        $this->assertStringContainsString('"type":"page"', $stored);
        $this->assertStringContainsString('"reference_id":', $stored);
        $this->assertStringNotContainsString('button_url', $stored);
        $this->assertStringNotContainsString('cta_url', $stored);
    }

    private function home(): Page
    {
        Setting::query()->updateOrCreate(
            ['key' => 'site_name'],
            ['value' => 'سایت آزمایشی', 'group' => 'general', 'type' => 'text'],
        );

        return Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'خانه',
            'blocks' => [],
        ]);
    }

    private function template(
        string $slug = 'industrial-header-v1',
        string $type = 'site_header',
        string $status = 'published',
        array $blocks = [],
        bool $isDefault = false,
    ): Template {
        return Template::query()->create([
            'title' => $slug === 'industrial-header-v1'
                ? 'هدر صنعتی دو سطحی'
                : $slug,
            'slug' => $slug,
            'type' => $type,
            'status' => $status,
            'is_default' => $isDefault,
            'conditions' => ['type' => 'all'],
            'blocks' => $blocks,
        ]);
    }

    private function select(Template $template): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'header_template_id'],
            [
                'value' => (string) $template->getKey(),
                'group' => 'header',
                'type' => 'select',
            ],
        );
    }

    private function menu(bool $includeUnavailableSource = false): Menu
    {
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'industrial-main',
            'location' => 'main',
            'status' => 'active',
        ]);
        MenuItem::query()->create([
            'menu_id' => $menu->getKey(),
            'title' => 'پروژه‌ها',
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => '/projects',
            'target' => '_self',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        if ($includeUnavailableSource) {
            MenuItem::query()->create([
                'menu_id' => $menu->getKey(),
                'title' => 'منبع ناموجود',
                'type' => MenuItem::TYPE_SOURCE,
                'source_key' => 'unknown.source',
                'target' => '_self',
                'sort_order' => 2,
                'status' => 'active',
            ]);
        }

        return $menu;
    }

    private function headerBlocks(?int $referenceId = null, ?int $menuId = null): array
    {
        $action = $referenceId
            ? [
                'schema_version' => 1,
                'type' => 'page',
                'reference_id' => $referenceId,
                'open_in_new_tab' => false,
            ]
            : null;

        return [[
            'type' => 'site_header',
            'data' => [
                'block_id' => self::BLOCK_ID,
                'schema_version' => 1,
                'template' => 'industrial-header-v1',
                'content' => [
                    'top_actions' => [
                        ['label' => 'خدمات و پشتیبانی', 'action' => $action],
                        ['label' => 'همکاری با ما', 'action' => $action],
                    ],
                    'primary_action' => [
                        'label' => 'محاسبه هزینه ساخت',
                        'action' => $action,
                    ],
                ],
                'settings' => [
                    'menu_id' => $menuId,
                    'search_enabled' => true,
                    'sticky_enabled' => true,
                    'top_bar_enabled' => true,
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
