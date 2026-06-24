<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsServiceTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame('6px', $variables['--theme-button-radius']);
        $this->assertSame('1180px', $variables['--theme-container-width']);
        $this->assertArrayHasKey('--theme-font-family', $variables);
    }

    public function test_invalid_theme_values_fall_back_to_safe_defaults(): void
    {
        Setting::query()->create(['key' => 'primary_color', 'value' => 'not-a-color', 'group' => 'theme', 'type' => 'color']);
        Setting::query()->create(['key' => 'button_radius', 'value' => 'bad-radius', 'group' => 'theme', 'type' => 'text']);

        $variables = app(SettingsService::class)->themeVariables();

        $this->assertSame('#2563eb', $variables['--theme-primary']);
        $this->assertSame('6px', $variables['--theme-button-radius']);
    }

    public function test_uploaded_custom_font_can_drive_theme_font_family(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/fonts/client.woff2', 'font-data');

        Setting::query()->create(['key' => 'font_family', 'value' => 'custom', 'group' => 'theme', 'type' => 'select']);
        Setting::query()->create(['key' => 'custom_font_name', 'value' => 'Client Font!', 'group' => 'theme', 'type' => 'text']);
        Setting::query()->create(['key' => 'custom_font_file', 'value' => 'settings/fonts/client.woff2', 'group' => 'theme', 'type' => 'file']);

        $settings = app(SettingsService::class);
        $variables = $settings->themeVariables();

        $this->assertStringStartsWith('"Client Font"', $variables['--theme-font-family']);
        $this->assertSame('/storage/settings/fonts/client.woff2', $settings->customFontUrl());
        $this->assertSame('woff2', $settings->customFontFormat());
    }
}
