<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use App\Services\SiteFontStyleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_settings_use_the_bundled_persian_font_before_system_fallbacks(): void
    {
        $bundledUrl = $this->installBundledFontFixture();

        $font = app(SiteFontStyleResolver::class)->resolve();
        $css = app(SiteFontStyleResolver::class)->css();

        $this->assertTrue($font['bundled_available']);
        $this->assertSame('CMS Default Persian', $font['bundled_family']);
        $this->assertSame($bundledUrl, $font['bundled_url']);
        $this->assertSame(1, substr_count($css, '@font-face'));
        $this->assertStringContainsString(
            'url("'.$bundledUrl.'") format("woff2")',
            $css,
        );
        $this->assertStringContainsString(
            '--site-font-family:"CMS Default Persian", system-ui',
            $css,
        );
        $this->assertStringContainsString('"Segoe UI", sans-serif', $css);
    }

    public function test_fallback_values_work(): void
    {
        $settings = app(SettingsService::class);

        $this->assertSame('Fallback value', $settings->get('missing_key', 'Fallback value'));

        Setting::query()->create([
            'key' => 'site_name',
            'value' => 'Configured Site',
            'group' => 'general',
            'type' => 'text',
        ]);

        $this->assertSame('Configured Site', $settings->siteName());
    }

    public function test_theme_variables_have_safe_defaults(): void
    {
        $variables = app(SettingsService::class)->themeVariables();

        $this->assertSame('#2563eb', $variables['--theme-primary']);
        $this->assertSame('#111827', $variables['--theme-secondary']);
        $this->assertSame('#1f2937', $variables['--theme-text']);
        $this->assertSame('#f8fafc', $variables['--theme-background']);
        $this->assertSame('16px', $variables['--theme-base-font-size']);
        $this->assertSame('16px', $variables['--theme-button-font-size']);
        $this->assertSame('24px', $variables['--theme-h1-font-size']);
        $this->assertSame('22px', $variables['--theme-h2-font-size']);
        $this->assertSame('20px', $variables['--theme-h3-font-size']);
        $this->assertSame('18px', $variables['--theme-h4-font-size']);
        $this->assertSame('10px', $variables['--theme-button-radius']);
        $this->assertSame('1200px', $variables['--theme-container-width']);
        $this->assertSame('15px', $variables['--theme-base-font-size-mobile']);
        $this->assertSame('15px', $variables['--theme-button-font-size-mobile']);
        $this->assertSame('22px', $variables['--theme-h1-font-size-mobile']);
        $this->assertSame('20px', $variables['--theme-h2-font-size-mobile']);
        $this->assertSame('18px', $variables['--theme-h3-font-size-mobile']);
        $this->assertSame('16px', $variables['--theme-h4-font-size-mobile']);
        $this->assertSame('10px', $variables['--theme-button-radius-mobile']);
        $this->assertSame('343px', $variables['--theme-container-width-mobile']);
        $this->assertArrayHasKey('--theme-font-family', $variables);
        $this->assertSame('#f0f0f0', app(SettingsService::class)->adminDashboardBackgroundLight());
    }

    public function test_admin_dashboard_light_background_uses_saved_valid_color(): void
    {
        Setting::query()->create([
            'key' => 'admin_dashboard_background_light',
            'value' => '#e0f2fe',
            'group' => 'theme',
            'type' => 'color',
        ]);

        $this->assertSame('#e0f2fe', app(SettingsService::class)->adminDashboardBackgroundLight());
    }

    public function test_invalid_theme_values_fall_back_to_safe_defaults(): void
    {
        Setting::query()->create(['key' => 'primary_color', 'value' => 'not-a-color', 'group' => 'theme', 'type' => 'color']);
        Setting::query()->create(['key' => 'button_radius', 'value' => 'bad-radius', 'group' => 'theme', 'type' => 'text']);

        $variables = app(SettingsService::class)->themeVariables();

        $this->assertSame('#2563eb', $variables['--theme-primary']);
        $this->assertSame('10px', $variables['--theme-button-radius']);
    }

    public function test_uploaded_custom_font_can_drive_theme_font_family(): void
    {
        Storage::fake('public');
        config()->set('app.url', 'http://localhost:8000');
        $bundledUrl = $this->installBundledFontFixture();
        Storage::disk('public')->put('settings/fonts/client.woff2', 'font-data');

        Setting::query()->create(['key' => 'font_family', 'value' => 'custom', 'group' => 'theme', 'type' => 'select']);
        Setting::query()->create(['key' => 'custom_font_name', 'value' => 'Client Font!', 'group' => 'theme', 'type' => 'text']);
        Setting::query()->create(['key' => 'custom_font_file', 'value' => 'settings/fonts/client.woff2', 'group' => 'theme', 'type' => 'file']);

        $settings = app(SettingsService::class);
        $variables = $settings->themeVariables();

        $this->assertStringStartsWith('"Client Font"', $variables['--theme-font-family']);
        $this->assertSame('/storage/settings/fonts/client.woff2', $settings->customFontUrl());
        $this->assertSame('woff2', $settings->customFontFormat());

        $css = app(SiteFontStyleResolver::class)->css();

        $this->assertSame(2, substr_count($css, '@font-face'));
        $this->assertStringContainsString('font-family:"CMS Default Persian"', $css);
        $this->assertStringContainsString('url("'.$bundledUrl.'") format("woff2")', $css);
        $this->assertStringContainsString('font-family:"Client Font"', $css);
        $this->assertStringContainsString('url("/storage/settings/fonts/client.woff2") format("woff2")', $css);
        $this->assertStringContainsString('font-display:swap', $css);
        $this->assertStringContainsString(
            '--site-font-family:"Client Font", "CMS Default Persian", system-ui',
            $css,
        );
        $this->assertStringNotContainsString('http://localhost:8000', $css);
    }

    public function test_intentionally_configured_external_font_url_is_preserved(): void
    {
        Setting::query()->create(['key' => 'font_family', 'value' => 'custom', 'group' => 'theme', 'type' => 'select']);
        Setting::query()->create(['key' => 'custom_font_name', 'value' => 'CDN Font', 'group' => 'theme', 'type' => 'text']);
        Setting::query()->create([
            'key' => 'custom_font_file',
            'value' => 'https://cdn.example.com/fonts/client.woff2?v=2',
            'group' => 'theme',
            'type' => 'file',
        ]);

        $font = app(SiteFontStyleResolver::class)->resolve();

        $this->assertSame('https://cdn.example.com/fonts/client.woff2?v=2', $font['url']);
        $this->assertStringContainsString(
            'url("https://cdn.example.com/fonts/client.woff2?v=2") format("woff2")',
            app(SiteFontStyleResolver::class)->css(),
        );
    }

    public function test_missing_or_invalid_custom_font_does_not_emit_a_broken_font_face(): void
    {
        Storage::fake('public');
        $bundledUrl = $this->installBundledFontFixture();

        Setting::query()->create(['key' => 'font_family', 'value' => 'custom', 'group' => 'theme', 'type' => 'select']);
        Setting::query()->create(['key' => 'custom_font_name', 'value' => 'Missing Font', 'group' => 'theme', 'type' => 'text']);
        Setting::query()->create(['key' => 'custom_font_file', 'value' => 'settings/fonts/missing.woff2', 'group' => 'theme', 'type' => 'file']);

        $font = app(SiteFontStyleResolver::class);

        $this->assertSame(1, substr_count($font->css(), '@font-face'));
        $this->assertStringNotContainsString('font-family:"Missing Font"', $font->css());
        $this->assertStringContainsString('url("'.$bundledUrl.'")', $font->css());
        $this->assertStringContainsString('--site-font-family:"CMS Default Persian", system-ui', $font->css());
        $this->assertNull(app(SettingsService::class)->customFontUrl());
    }

    public function test_invalid_uploaded_extension_falls_back_to_the_bundled_font(): void
    {
        Storage::fake('public');
        $this->installBundledFontFixture();
        Storage::disk('public')->put('settings/fonts/not-a-font.txt', 'not-font-data');

        Setting::query()->create(['key' => 'font_family', 'value' => 'custom', 'group' => 'theme', 'type' => 'select']);
        Setting::query()->create(['key' => 'custom_font_name', 'value' => 'Rejected Font', 'group' => 'theme', 'type' => 'text']);
        Setting::query()->create(['key' => 'custom_font_file', 'value' => 'settings/fonts/not-a-font.txt', 'group' => 'theme', 'type' => 'file']);

        $css = app(SiteFontStyleResolver::class)->css();

        $this->assertSame(1, substr_count($css, '@font-face'));
        $this->assertStringNotContainsString('Rejected Font', $css);
        $this->assertStringContainsString('--site-font-family:"CMS Default Persian", system-ui', $css);
    }

    public function test_legacy_uploaded_font_values_are_resolved_without_changing_canonical_keys(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/fonts/legacy.woff2', 'font-data');

        Setting::query()->create(['key' => 'font_mode', 'value' => 'uploaded', 'group' => 'theme', 'type' => 'select']);
        Setting::query()->create(['key' => 'font_name', 'value' => 'Legacy Font', 'group' => 'theme', 'type' => 'text']);
        Setting::query()->create([
            'key' => 'custom_font_path',
            'value' => json_encode(['/storage/settings/fonts/legacy.woff2']),
            'group' => 'theme',
            'type' => 'file',
        ]);

        $font = app(SiteFontStyleResolver::class)->resolve();

        $this->assertSame('custom', $font['mode']);
        $this->assertSame('Legacy Font', $font['name']);
        $this->assertSame('settings/fonts/legacy.woff2', $font['path']);
        $this->assertSame('/storage/settings/fonts/legacy.woff2', $font['url']);
    }

    public function test_font_css_cannot_be_injected_through_name_or_file_value(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/fonts/safe.woff2', 'font-data');

        Setting::query()->create(['key' => 'font_family', 'value' => 'custom', 'group' => 'theme', 'type' => 'select']);
        Setting::query()->create(['key' => 'custom_font_name', 'value' => 'Ravi";color:red;/*', 'group' => 'theme', 'type' => 'text']);
        Setting::query()->create(['key' => 'custom_font_file', 'value' => 'https://evil.test/font.woff2");color:red;/*', 'group' => 'theme', 'type' => 'file']);

        $css = app(SiteFontStyleResolver::class)->css();

        $this->assertStringNotContainsString('@font-face', $css);
        $this->assertStringNotContainsString('evil.test', $css);
        $this->assertStringNotContainsString('color:red', $css);
    }

    public function test_frontend_and_filament_use_the_same_canonical_font_styles(): void
    {
        Storage::fake('public');
        $this->installBundledFontFixture();
        Storage::disk('public')->put('settings/fonts/shared.woff2', 'font-data');

        Setting::query()->create(['key' => 'font_family', 'value' => 'custom', 'group' => 'theme', 'type' => 'select']);
        Setting::query()->create(['key' => 'custom_font_name', 'value' => 'Shared Font', 'group' => 'theme', 'type' => 'text']);
        Setting::query()->create(['key' => 'custom_font_file', 'value' => 'settings/fonts/shared.woff2', 'group' => 'theme', 'type' => 'file']);

        $frontend = view('partials.theme')->render();
        $admin = view('filament.theme')->render();

        foreach ([$frontend, $admin] as $html) {
            $this->assertSame(2, substr_count($html, '@font-face'));
            $this->assertStringContainsString(
                '--site-font-family:"Shared Font", "CMS Default Persian", system-ui',
                $html,
            );
            $this->assertStringContainsString('/storage/settings/fonts/shared.woff2', $html);
        }

        $previewTemplate = File::get(resource_path('views/filament/forms/components/theme-live-preview.blade.php'));

        $this->assertStringContainsString('SiteFontStyleResolver', $previewTemplate);
        $this->assertStringContainsString('resolvedSiteFontFamily', $previewTemplate);
        $this->assertStringNotContainsString('fontFamily()', $previewTemplate);
        $this->assertStringContainsString('font-family: var(--site-font-family)', $admin);
        $this->assertStringContainsString('.fi .font-sans', $admin);
    }

    public function test_missing_bundled_font_safely_uses_the_system_stack(): void
    {
        config()->set('cms.default_font.filename', 'missing-cms-default-test.woff2');

        $font = app(SiteFontStyleResolver::class)->resolve();
        $css = app(SiteFontStyleResolver::class)->css();

        $this->assertFalse($font['bundled_available']);
        $this->assertNull($font['bundled_url']);
        $this->assertStringNotContainsString('@font-face', $css);
        $this->assertStringContainsString('--site-font-family:system-ui', $css);
    }

    public function test_malformed_bundled_configuration_cannot_inject_css(): void
    {
        config()->set('cms.default_font.family', 'Bad";color:red;/*');
        config()->set('cms.default_font.filename', '../bad.woff2');

        $css = app(SiteFontStyleResolver::class)->css();

        $this->assertStringNotContainsString('@font-face', $css);
        $this->assertStringNotContainsString('color:red', $css);
        $this->assertStringNotContainsString('../bad.woff2', $css);
        $this->assertStringContainsString('--site-font-family:system-ui', $css);
    }

    private function installBundledFontFixture(): string
    {
        $filename = 'cms-default-persian-test.woff2';
        $directory = public_path('fonts');
        $path = $directory.'/'.$filename;

        config()->set('cms.default_font.filename', $filename);
        File::ensureDirectoryExists($directory);
        File::put($path, 'test-font-data');

        $this->beforeApplicationDestroyed(function () use ($directory, $path): void {
            File::delete($path);
            @rmdir($directory);
        });

        return '/fonts/'.$filename;
    }
}
