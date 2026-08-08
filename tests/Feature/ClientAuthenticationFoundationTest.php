<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_log_in_with_mobile_and_password(): void
    {
        $client = User::factory()->client()->create(['password' => 'secret-password']);

        $this->post('/login', [
            'mobile' => $client->mobile,
            'password' => 'secret-password',
        ])->assertRedirect(route('client.dashboard'));

        $this->assertAuthenticatedAs($client, 'client');
        $this->get('/dashboard')->assertOk()->assertSee($client->name);
    }

    public function test_client_mobile_is_normalized_for_storage_and_login(): void
    {
        $client = User::factory()->client()->create([
            'mobile' => '+98 912 345 6789',
            'password' => 'secret-password',
        ]);

        $this->assertSame('09123456789', $client->mobile);

        $this->post('/login', [
            'mobile' => '۰۹۱۲-۳۴۵-۶۷۸۹',
            'password' => 'secret-password',
        ])->assertRedirect(route('client.dashboard'));

        $this->assertAuthenticatedAs($client, 'client');
    }

    public function test_admin_cannot_access_client_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'client')
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_client_is_redirected_to_client_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_existing_admin_can_still_access_filament_panel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk();
    }

    public function test_client_can_update_profile_without_changing_role_or_mobile(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client, 'client')->patch('/profile', [
            'name' => 'Updated Client',
            'email' => 'updated@example.com',
            'mobile' => '0000000000',
            'is_admin' => true,
            'status' => 'inactive',
        ])->assertRedirect();

        $client->refresh();

        $this->assertSame('Updated Client', $client->name);
        $this->assertSame('updated@example.com', $client->email);
        $this->assertNotSame('0000000000', $client->mobile);
        $this->assertFalse($client->is_admin);
        $this->assertSame('active', $client->status);
    }
}
