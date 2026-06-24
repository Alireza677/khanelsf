<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Redirect;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_route_returns_safe_status_without_secrets(): void
    {
        config([
            'app.name' => 'Starter CMS',
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'database.connections.sqlite.password' => 'secret-db-password',
        ]);

        $this->get(route('health'))
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'app' => 'Starter CMS',
                'database' => 'ok',
            ])
            ->assertJsonMissingPath('debug')
            ->assertJsonMissingPath('key')
            ->assertDontSee(base64_encode(str_repeat('a', 32)))
            ->assertDontSee('secret-db-password');
    }

    public function test_health_route_can_be_disabled_from_settings(): void
    {
        Setting::query()->create([
            'key' => 'health_check_enabled',
            'value' => '0',
            'group' => 'general',
            'type' => 'boolean',
        ]);

        $this->get(route('health'))->assertNotFound();
    }

    public function test_guest_cannot_access_launch_checklist(): void
    {
        $this->get('/admin/launch-checklist')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_access_launch_checklist(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
            'mail.mailers.smtp.password' => 'secret-smtp-password',
        ]);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/launch-checklist')
            ->assertOk()
            ->assertSee('System status / launch checklist')
            ->assertSee('System status')
            ->assertSee('Backup checklist')
            ->assertSee('Site name')
            ->assertSee('Health check')
            ->assertDontSee(base64_encode(str_repeat('b', 32)))
            ->assertDontSee('secret-smtp-password');
    }

    public function test_guest_cannot_download_maintenance_exports(): void
    {
        $this->get(route('admin.exports.contact-messages'))
            ->assertRedirect('/admin/login');

        $this->get(route('admin.exports.redirects'))
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_download_contact_messages_csv(): void
    {
        ContactMessage::query()->create([
            'name' => 'Client User',
            'email' => 'client@example.com',
            'phone' => '555',
            'subject' => 'Project',
            'message' => 'Hello',
            'status' => 'new',
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.exports.contact-messages'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('client@example.com', $response->streamedContent());
    }

    public function test_admin_can_download_redirects_csv(): void
    {
        Redirect::query()->create([
            'source_path' => '/old',
            'target_url' => '/new',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->get(route('admin.exports.redirects'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('/old', $response->streamedContent());
    }
}
