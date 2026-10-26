<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use App\Services\SettingsService;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomepageHeaderIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Exercise the production cache path, which SettingsService normally disables in tests.
        HomepageHeaderCachedSettings::resetCache();
        $this->app->bind(SettingsService::class, HomepageHeaderCachedSettings::class);
    }

    protected function tearDown(): void
    {
        HomepageHeaderCachedSettings::resetCache();

        parent::tearDown();
    }

    public function test_admin_selected_homepage_renders_through_the_normal_page_view(): void
    {
        Page::factory()->published()->create(['slug' => 'home', 'content' => '<p>Legacy homepage fixture</p>']);
        $page = Page::factory()->published()->create([
            'slug' => 'selected-landing',
            'template' => 'default',
            'blocks' => [['type' => 'hero', 'data' => ['title' => 'Selected builder fixture']]],
        ]);
        $this->saveSettings(['homepage_page_id' => $page->id]);

        $this->assertDatabaseHas('settings', [
            'key' => 'homepage_page_id', 'value' => (string) $page->id,
            'group' => 'general', 'type' => 'select',
        ]);
        $this->get('/')->assertOk()->assertViewIs('page')
            ->assertSee('Selected builder fixture')->assertDontSee('Legacy homepage fixture')
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false);
    }

    public function test_changing_homepage_in_admin_invalidates_warmed_settings(): void
    {
        $first = Page::factory()->published()->create(['content' => '<p>First homepage fixture</p>']);
        $second = Page::factory()->published()->create(['content' => '<p>Second homepage fixture</p>']);
        $this->saveSettings(['homepage_page_id' => $first->id]);
        $this->get('/')->assertOk()->assertSee('First homepage fixture');
        app(SettingsService::class)->many(['homepage_page_id']);

        $this->saveSettings(['homepage_page_id' => $second->id]);

        $this->get('/')->assertOk()->assertSee('Second homepage fixture')->assertDontSee('First homepage fixture');
        $this->get('/'.$first->slug)->assertOk()->assertSee('First homepage fixture');
    }

    public function test_homepage_and_direct_page_preserve_identical_trusted_html_css_and_js(): void
    {
        $code = '<style>.homepage-fixture{color:purple}</style><div class="homepage-fixture">Trusted fixture</div><script>window.homepageFixture=true;</script>';
        $page = Page::factory()->published()->create([
            'blocks' => [['type' => 'custom_html', 'data' => ['code' => $code]]],
        ]);
        app(SettingsService::class)->set('homepage_page_id', $page->id, 'general', 'select');

        $home = $this->get('/')->assertOk()->assertViewIs('page')->assertSee($code, false)->getContent();
        $direct = $this->get('/'.$page->slug)->assertOk()->assertViewIs('page')->assertSee($code, false)->getContent();
        preg_match('/<section class="content-block custom-html-block">.*?<\/section>/s', $home, $homeBlock);
        preg_match('/<section class="content-block custom-html-block">.*?<\/section>/s', $direct, $directBlock);
        $this->assertNotEmpty($homeBlock);
        $this->assertSame($directBlock[0], $homeBlock[0]);
    }

    #[DataProvider('unavailablePages')]
    public function test_selected_unavailable_page_uses_the_publication_policy(array $attributes): void
    {
        Page::factory()->published()->create(['slug' => 'home', 'content' => '<p>Unrelated legacy fixture</p>']);
        $page = Page::factory()->published()->create($attributes);
        app(SettingsService::class)->set('homepage_page_id', $page->id, 'general', 'select');

        $this->get('/')->assertNotFound()->assertDontSee('Unrelated legacy fixture');
        $this->get('/'.$page->slug)->assertNotFound();
    }

    public static function unavailablePages(): array
    {
        return [
            'draft' => [['status' => 'draft']],
            'inactive' => [['status' => 'inactive']],
            'scheduled' => [['published_at' => '2099-01-01 00:00:00']],
        ];
    }

    public function test_deleted_selected_page_does_not_fall_back_to_other_content(): void
    {
        $page = Page::factory()->published()->create();
        app(SettingsService::class)->set('homepage_page_id', $page->id, 'general', 'select');
        $this->get('/')->assertOk();
        $page->delete();

        $this->get('/')->assertNotFound();
        $this->get('/'.$page->slug)->assertNotFound();
    }

    public function test_unconfigured_sites_keep_the_legacy_homepage_and_empty_site_behavior(): void
    {
        $this->get('/')->assertOk()->assertViewIs('home');
        $page = Page::factory()->published()->create(['slug' => 'home', 'content' => '<p>Legacy fixture</p>']);
        $this->get('/')->assertOk()->assertViewIs('home')->assertSee('Legacy fixture');
        app(SettingsService::class)->set('homepage_page_id', $page->id, 'general', 'select');
        $this->get('/')->assertViewIs('page');
        $this->saveSettings(['homepage_page_id' => null]);
        $this->get('/')->assertOk()->assertViewIs('home')->assertSee('Legacy fixture');
    }

    public function test_homepage_selector_lists_only_public_pages_and_rejects_missing_ids(): void
    {
        $public = Page::factory()->published()->create();
        Page::factory()->draft()->create();
        Page::factory()->published()->create(['published_at' => now()->addDay()]);
        $this->actingAs(User::factory()->admin()->create());
        $component = Livewire::test(ManageSiteSettings::class);
        $selector = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Select && $field->getName() === 'homepage_page_id');

        $this->assertInstanceOf(Select::class, $selector);
        $this->assertSame([$public->id => $public->title], $selector->getOptions());
        $component->set('data.site_name', 'Fixture site')->set('data.homepage_page_id', 999999)
            ->call('save')->assertHasFormErrors(['homepage_page_id']);
    }

    public function test_site_header_menu_selection_takes_precedence_over_a_template_menu(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        $old = $this->menu('Template menu fixture');
        $selected = $this->menu('Selected header fixture');
        $this->headerTemplate($old->id);
        $this->saveSettings(['header_menu_id' => $selected->id]);

        $this->assertHeaderContains('Selected header fixture', 'Template menu fixture');
    }

    #[DataProvider('headerVariants')]
    public function test_changing_header_menu_in_admin_invalidates_warmed_settings(bool $template): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        if ($template) {
            $this->headerTemplate();
        }
        $first = $this->menu('First navigation fixture');
        $second = $this->menu('Second navigation fixture');
        $this->saveSettings(['header_menu_id' => $first->id]);
        $this->assertHeaderContains('First navigation fixture', 'Second navigation fixture');
        app(SettingsService::class)->many(['header_menu_id']);

        $this->saveSettings(['header_menu_id' => $second->id]);

        $this->assertHeaderContains('Second navigation fixture', 'First navigation fixture');
    }

    #[DataProvider('headerVariants')]
    public function test_header_keeps_nested_order_visibility_and_source_resolution(bool $template): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        if ($template) {
            $this->headerTemplate();
        }
        $menu = $this->menu('Second ordered fixture');
        $parent = $menu->items()->create([
            'title' => 'First ordered fixture', 'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => '/fixture-parent', 'sort_order' => -1, 'status' => 'active', 'target' => '_self',
        ]);
        $destination = Page::factory()->published()->create(['slug' => 'fixture-child']);
        $menu->items()->create([
            'title' => 'Nested reference fixture', 'type' => MenuItem::TYPE_PAGE,
            'reference_id' => $destination->id, 'reference_type' => $destination->getMorphClass(),
            'parent_id' => $parent->id, 'status' => 'active', 'target' => '_blank',
        ]);
        foreach (['unknown.fixture', 'galleries.archive'] as $source) {
            $menu->items()->create([
                'title' => $source, 'type' => MenuItem::TYPE_SOURCE, 'source_key' => $source,
                'status' => 'active', 'target' => '_self',
            ]);
        }
        $menu->items()->create([
            'title' => 'Inactive item fixture', 'url' => '/hidden-fixture', 'status' => 'inactive',
        ]);
        app(SettingsService::class)->set('header_menu_id', $menu->id, 'header', 'select');

        $html = $this->headerHtml();
        $this->assertStringContainsString('href="/fixture-child"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('href="/galleries"', $html);
        $this->assertStringNotContainsString('unknown.fixture', $html);
        $this->assertStringNotContainsString('Inactive item fixture', $html);
        $this->assertLessThan(strpos($html, 'Second ordered fixture'), strpos($html, 'First ordered fixture'));
        $this->assertMatchesRegularExpression('/First ordered fixture.*?<ul[^>]*>.*?Nested reference fixture/s', $html);

        $destination->update(['slug' => 'fixture-renamed']);
        $this->assertStringContainsString('href="/fixture-renamed"', $this->headerHtml());
        app(SettingsService::class)->set('projects_enabled', false, 'projects', 'boolean');
        $this->assertStringNotContainsString('galleries.archive', $this->headerHtml());
    }

    public function test_missing_inactive_and_deleted_selected_menus_do_not_render_an_unrelated_menu(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        $legacy = $this->menu('Legacy menu fixture', 'main');
        $this->headerTemplate($legacy->id);
        $settings = app(SettingsService::class);
        $this->assertStringContainsString('Legacy menu fixture', $this->headerHtml());
        $selected = $this->menu('Temporary menu fixture');
        $settings->set('header_menu_id', $selected->id, 'header', 'select');
        $selected->update(['status' => 'inactive']);
        $this->assertStringNotContainsString('Legacy menu fixture', $this->headerHtml());
        $this->assertStringNotContainsString('Temporary menu fixture', $this->headerHtml());
        $selected->delete();
        $this->assertStringNotContainsString('Legacy menu fixture', $this->headerHtml());
        $settings->set('header_menu_id', null, 'header', 'select');
        $this->assertStringContainsString('Legacy menu fixture', $this->headerHtml());
        $settings->set('header_template_id', null, 'header', 'select');
        $this->assertStringContainsString('Legacy menu fixture', $this->headerHtml());
        $legacy->delete();
        $this->assertStringNotContainsString('Legacy menu fixture', $this->headerHtml());
    }

    public static function headerVariants(): array
    {
        return ['default header' => [false], 'industrial template' => [true]];
    }

    private function saveSettings(array $values): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $component = Livewire::test(ManageSiteSettings::class)->set('data.site_name', 'Fixture site');
        foreach ($values as $key => $value) {
            $component->set('data.'.$key, $value);
        }
        $component->call('save')->assertHasNoFormErrors();
    }

    private function menu(string $title, ?string $location = null): Menu
    {
        $menu = Menu::query()->create([
            'title' => $title, 'slug' => str($title)->slug()->toString(),
            'location' => $location, 'status' => 'active',
        ]);
        $menu->items()->create([
            'title' => $title, 'url' => '/fixture-link', 'type' => MenuItem::TYPE_CUSTOM_URL,
            'status' => 'active', 'target' => '_self', 'sort_order' => 10,
        ]);

        return $menu;
    }

    private function headerTemplate(?int $menuId = null): void
    {
        $template = Template::query()->create([
            'title' => 'Header fixture', 'slug' => 'header-fixture', 'type' => 'site_header',
            'status' => 'published', 'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'site_header', 'data' => ['settings' => ['menu_id' => $menuId]]]],
        ]);
        app(SettingsService::class)->set('header_template_id', $template->id, 'header', 'select');
    }

    private function headerHtml(): string
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<header\b.*?<\/header>/s', $html, $header);
        $this->assertNotEmpty($header);

        return $header[0];
    }

    private function assertHeaderContains(string $visible, string $hidden): void
    {
        $html = $this->headerHtml();
        $this->assertStringContainsString($visible, $html);
        $this->assertStringNotContainsString($hidden, $html);
    }
}

class HomepageHeaderCachedSettings extends SettingsService
{
    protected static function requestCacheEnabled(): bool
    {
        return true;
    }

    public static function resetCache(): void
    {
        static::$requestCache = [];
    }
}
