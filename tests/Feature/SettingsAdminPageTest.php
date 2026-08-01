<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Menu;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SettingsAdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_settings_page_redirects_to_site_settings_page(): void
    {
        $response = $this
            ->actingAs(User::factory()->admin()->create())
            ->get('/admin/settings');

        $response->assertRedirect('/admin/manage-site-settings');
    }

    public function test_raw_setting_mutation_routes_are_not_registered(): void
    {
        $this
            ->actingAs(User::factory()->admin()->create())
            ->get('/admin/settings/create')
            ->assertNotFound();

        $this
            ->get('/admin/settings/1/edit')
            ->assertNotFound();
    }

    public function test_admin_can_save_the_light_dashboard_background_from_site_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSiteSettings::class)
            ->set('data.site_name', 'سایت آزمایشی')
            ->set('data.admin_dashboard_background_light', '#dbeafe')
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = Setting::query()->where('key', 'admin_dashboard_background_light')->sole();

        $this->assertSame('#dbeafe', $setting->value);
        $this->assertSame('theme', $setting->group);
        $this->assertSame('color', $setting->type);
        $this->assertStringContainsString(
            '--admin-dashboard-background-light: #dbeafe;',
            view('filament.theme')->render(),
        );
    }

    public function test_saving_legacy_font_state_writes_the_canonical_file_path_shape(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSiteSettings::class)
            ->set('data.site_name', 'سایت آزمایشی')
            ->set('data.font_family', 'custom')
            ->set('data.custom_font_name', 'Legacy Font')
            ->set('data.custom_font_file', ['settings/fonts/legacy.woff2'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'custom_font_file',
            'value' => 'settings/fonts/legacy.woff2',
            'group' => 'theme',
            'type' => 'file',
        ]);
    }

    public function test_legacy_non_custom_font_mode_uses_the_default_mode_without_rewriting_storage(): void
    {
        Setting::query()->create([
            'key' => 'font_family',
            'value' => 'serif',
            'group' => 'theme',
            'type' => 'select',
        ]);

        Livewire::test(ManageSiteSettings::class)
            ->assertSet('data.font_family', 'system');

        $this->assertDatabaseHas('settings', [
            'key' => 'font_family',
            'value' => 'serif',
        ]);
    }

    public function test_header_and_footer_menus_can_be_selected_from_site_settings(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $headerMenu = Menu::query()->create([
            'title' => 'منوی هدر',
            'slug' => 'selected-header',
            'status' => 'active',
        ]);
        $footerMenu = Menu::query()->create([
            'title' => 'منوی فوتر',
            'slug' => 'selected-footer',
            'status' => 'active',
        ]);

        Livewire::test(ManageSiteSettings::class)
            ->set('data.site_name', 'سایت آزمایشی')
            ->set('data.header_menu_id', $headerMenu->getKey())
            ->set('data.footer_menu_id', $footerMenu->getKey())
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'header_menu_id',
            'value' => (string) $headerMenu->getKey(),
            'group' => 'header',
            'type' => 'select',
        ]);
        $this->assertDatabaseHas('settings', [
            'key' => 'footer_menu_id',
            'value' => (string) $footerMenu->getKey(),
            'group' => 'footer',
            'type' => 'select',
        ]);
    }
}
