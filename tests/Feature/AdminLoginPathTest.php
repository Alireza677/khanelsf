<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Support\AdminLoginPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_login_path_works_without_a_setting(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('email', false);
    }

    public function test_custom_single_segment_path_replaces_old_login_path(): void
    {
        $this->setPath('king-secure-login');

        $this->get('/king-secure-login')->assertOk()->assertSee('email', false);
        $this->get('/admin/login')->assertNotFound();
        $this->get('/admin')->assertRedirect('/king-secure-login');
    }

    public function test_custom_multi_segment_path_works(): void
    {
        $this->setPath('management/access');

        $this->get('/management/access')->assertOk()->assertSee('email', false);
        $this->get('/management/wrong')->assertNotFound();
    }

    public function test_path_change_is_effective_on_the_next_request(): void
    {
        $this->setPath('king-secure-login');
        $this->get('/king-secure-login')->assertOk();

        $this->setPath('private-manager');

        $this->get('/private-manager')->assertOk();
        $this->get('/king-secure-login')->assertNotFound();
    }

    public function test_admin_session_survives_a_path_change_and_logout_uses_new_path(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->setPath('private-manager');
        $this->get('/admin')->assertOk();
        $this->assertAuthenticatedAs($admin);

        $this->post('/admin/logout')->assertRedirect('/private-manager');
        $this->assertGuest();
    }

    public function test_invalid_and_colliding_paths_are_rejected(): void
    {
        Page::query()->create([
            'title' => 'Existing page',
            'slug' => 'existing-page',
            'status' => 'published',
        ]);

        foreach (['https://example.com/login', '//example.com', '../admin', 'admin?x=1', 'admin#login', 'login', 'admin', 'livewire/update', 'existing-page'] as $path) {
            try {
                app(AdminLoginPath::class)->validate($path);
                $this->fail("Path [{$path}] should be invalid.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('data.admin_login_path', $exception->errors());
            }
        }

        $this->assertSame('secure-admin', app(AdminLoginPath::class)->validate('/secure-admin/'));
        $this->assertSame('manager/login', app(AdminLoginPath::class)->validate('manager/login'));
    }

    public function test_settings_page_normalizes_and_persists_the_path(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ManageSiteSettings::class)
            ->set('data.admin_login_path', '/secure-admin/')
            ->set('data.site_name', 'Test site')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'admin_login_path',
            'value' => 'secure-admin',
        ]);
    }

    private function setPath(string $path): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'admin_login_path'],
            ['value' => $path, 'group' => 'general', 'type' => 'text'],
        );
    }
}
