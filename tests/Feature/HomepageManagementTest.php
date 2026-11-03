<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\SettingsService;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HomepageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        HomepageManagementCachedSettings::resetCache();
        $this->app->bind(SettingsService::class, HomepageManagementCachedSettings::class);
        $this->actingAs(User::factory()->admin()->create());
    }

    protected function tearDown(): void
    {
        HomepageManagementCachedSettings::resetCache();

        parent::tearDown();
    }

    public function test_new_pages_cannot_select_or_submit_the_legacy_home_template(): void
    {
        $component = Livewire::test(CreatePage::class);
        $selector = $this->templateSelector($component);
        $this->assertSame(['default' => 'پیش‌فرض', 'landing' => 'لندینگ'], $selector->getOptions());

        $component->fillForm(['title' => 'New fixture', 'slug' => 'new-fixture', 'template' => 'home'])
            ->call('create')->assertHasFormErrors(['template']);
        $this->assertDatabaseMissing('pages', ['slug' => 'new-fixture']);
    }

    public function test_legacy_home_template_remains_stored_when_an_existing_page_is_saved(): void
    {
        $page = Page::factory()->published()->create([
            'slug' => 'legacy-template-fixture', 'template' => 'home', 'content' => '<p>Legacy template fixture</p>',
        ]);
        $component = Livewire::test(EditPage::class, ['record' => $page->id]);
        $selector = $this->templateSelector($component);
        $this->assertSame('قالب قدیمی (home)', $selector->getOptions()['home']);
        $this->assertTrue($selector->isOptionDisabled('home', 'قالب قدیمی (home)'));
        $component->set('data.title', 'Edited legacy fixture')->call('save')->assertHasNoFormErrors();

        $this->assertSame('home', $page->fresh()->template);
        $this->get('/legacy-template-fixture')->assertOk()->assertSee('Legacy template fixture');
        $this->assertDatabaseMissing('settings', ['key' => 'homepage_page_id']);
    }

    public function test_only_the_explicitly_selected_page_shows_the_homepage_notice(): void
    {
        $selected = Page::factory()->published()->create(['slug' => 'selected-fixture', 'template' => 'landing']);
        $legacy = Page::factory()->published()->create(['slug' => 'home', 'template' => 'home']);
        app(SettingsService::class)->set('homepage_page_id', $selected->id, 'general', 'select');

        Livewire::test(EditPage::class, ['record' => $selected->id])
            ->assertSee('این برگه به‌عنوان صفحه اصلی سایت انتخاب شده است.')
            ->assertSee(ManageSiteSettings::getUrl(['tab' => 'site-settings-general-tab']), false);
        Livewire::test(EditPage::class, ['record' => $legacy->id])
            ->assertDontSee('این برگه به‌عنوان صفحه اصلی سایت انتخاب شده است.');
    }

    public function test_replacing_homepage_requires_confirmation_and_cancellation_preserves_settings(): void
    {
        $first = Page::factory()->published()->create(['title' => 'Previous homepage fixture']);
        $next = Page::factory()->published()->create(['title' => 'Next homepage fixture']);
        $settings = app(SettingsService::class);
        $settings->set('site_name', 'Original site fixture');
        $settings->set('homepage_page_id', $first->id, 'general', 'select');
        $component = Livewire::test(ManageSiteSettings::class)
            ->set('data.site_name', 'Pending site fixture')
            ->set('data.homepage_page_id', $next->id)
            ->call('save')->assertHasNoFormErrors()
            ->assertActionMounted('confirmHomepageReplacement')
            ->assertSee('Previous homepage fixture')->assertSee('Next homepage fixture');
        $this->assertSame($first->id, $settings->homepagePageId());
        $this->assertSame('Original site fixture', $settings->siteName());

        $component->unmountAction();
        $this->assertSame($first->id, $settings->homepagePageId());
        $component->call('save')->callMountedAction()->assertHasNoFormErrors();

        $this->assertSame($next->id, $settings->homepagePageId());
        $this->assertSame('Pending site fixture', $settings->siteName());
        $this->assertSame(1, Setting::query()->where('key', 'homepage_page_id')->count());
    }

    #[DataProvider('ordinaryTemplates')]
    public function test_homepage_selection_is_independent_of_template_and_does_not_modify_page_data(string $template): void
    {
        $page = Page::factory()->published()->create([
            'slug' => 'ordinary-'.$template, 'template' => $template,
            'blocks' => [['type' => 'custom_html', 'data' => [
                'code' => '<style>.fixture{color:red}</style><div>Homepage management fixture</div><script>window.homepageFixture=1;</script>',
            ]]],
        ]);
        $before = $page->fresh()->getAttributes();
        $this->get('/')->assertDontSee('Homepage management fixture');
        $this->saveSettings($page->id);
        $this->assertSame($before, $page->fresh()->getAttributes());
        $this->get('/')->assertOk()->assertViewIs('page')->assertSee('Homepage management fixture')
            ->assertSee('<script>window.homepageFixture=1;</script>', false)
            ->assertSee('<style>.fixture{color:red}</style>', false);

        Livewire::test(EditPage::class, ['record' => $page->id])
            ->set('data.template', $template === 'default' ? 'landing' : 'default')
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($page->id, app(SettingsService::class)->homepagePageId());
        $this->get('/')->assertOk()->assertSee('Homepage management fixture');
    }

    public static function ordinaryTemplates(): array
    {
        return ['default' => ['default'], 'landing' => ['landing']];
    }

    public function test_new_homepage_replaces_the_old_one_with_warmed_cache_and_survives_reload(): void
    {
        $first = Page::factory()->published()->create(['slug' => 'first-fixture', 'content' => '<p>First selected fixture</p>']);
        $next = Page::factory()->published()->create(['slug' => 'next-fixture', 'content' => '<p>Next selected fixture</p>']);
        $this->saveSettings($first->id);
        $this->get('/')->assertOk()->assertSee('First selected fixture');
        app(SettingsService::class)->many(['homepage_page_id']);
        $this->saveSettings($next->id);

        $this->get('/')->assertOk()->assertSee('Next selected fixture')->assertDontSee('First selected fixture');
        $this->assertSame($next->id, app(SettingsService::class)->homepagePageId());
        HomepageManagementCachedSettings::resetCache();
        $this->assertSame($next->id, app(SettingsService::class)->homepagePageId());
        Livewire::test(ManageSiteSettings::class)->assertSet('data.homepage_page_id', (string) $next->id);
    }

    #[DataProvider('invalidSelections')]
    public function test_explicit_invalid_values_do_not_activate_the_legacy_fallback(string $value): void
    {
        Page::factory()->published()->create(['slug' => 'home', 'content' => '<p>Unrelated fallback fixture</p>']);
        app(SettingsService::class)->set('homepage_page_id', $value, 'general', 'select');

        $this->get('/')->assertNotFound()->assertDontSee('Unrelated fallback fixture');
    }

    public static function invalidSelections(): array
    {
        return ['non-numeric' => ['invalid'], 'zero' => ['0'], 'negative' => ['-1'], 'missing page' => ['999999']];
    }

    public function test_absent_or_cleared_selection_keeps_the_legacy_fallback(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'home', 'template' => 'home', 'content' => '<p>Fallback fixture</p>']);
        $this->get('/')->assertOk()->assertViewIs('home')->assertSee('Fallback fixture');
        $this->saveSettings($page->id);
        $this->get('/')->assertOk()->assertViewIs('page');
        $this->saveSettings(null);
        $this->get('/')->assertOk()->assertViewIs('home')->assertSee('Fallback fixture');
    }

    public function test_only_published_pages_can_be_submitted_as_a_new_selection(): void
    {
        foreach ([['status' => 'draft'], ['published_at' => now()->addDay()]] as $attributes) {
            $page = Page::factory()->published()->create($attributes);
            Livewire::test(ManageSiteSettings::class)->set('data.site_name', 'Fixture site')
                ->set('data.homepage_page_id', $page->id)->call('save')
                ->assertHasFormErrors(['homepage_page_id']);
        }
        $this->assertDatabaseMissing('settings', ['key' => 'homepage_page_id']);
    }

    public function test_confirmation_revalidates_publication_before_writing_settings(): void
    {
        $previous = Page::factory()->published()->create();
        $next = Page::factory()->published()->create();
        $settings = app(SettingsService::class);
        $settings->set('homepage_page_id', $previous->id, 'general', 'select');
        $component = Livewire::test(ManageSiteSettings::class)->set('data.site_name', 'Fixture site')
            ->set('data.homepage_page_id', $next->id)->call('save')
            ->assertActionMounted('confirmHomepageReplacement');
        $next->update(['status' => 'draft']);

        $component->callMountedAction()->assertHasFormErrors(['homepage_page_id']);

        $this->assertSame($previous->id, $settings->homepagePageId());
    }

    public function test_saving_the_same_homepage_does_not_prompt_for_replacement(): void
    {
        $page = Page::factory()->published()->create();
        app(SettingsService::class)->set('homepage_page_id', $page->id, 'general', 'select');

        $component = Livewire::test(ManageSiteSettings::class)->set('data.site_name', 'Updated fixture site')
            ->call('save')->assertHasNoFormErrors();

        $this->assertNull($component->instance()->getMountedAction());
        $this->assertSame('Updated fixture site', app(SettingsService::class)->siteName());
    }

    private function saveSettings(?int $id): void
    {
        $component = Livewire::test(ManageSiteSettings::class)->set('data.site_name', 'Fixture site')
            ->set('data.homepage_page_id', $id)->call('save')->assertHasNoFormErrors();
        if ($component->instance()->getMountedAction()) {
            $component->callMountedAction()->assertHasNoFormErrors();
        }
    }

    private function templateSelector($component): Select
    {
        return collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Select && $field->getName() === 'template');
    }
}

class HomepageManagementCachedSettings extends SettingsService
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
