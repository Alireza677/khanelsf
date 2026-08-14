<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientAuthenticationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_links_to_public_registration(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('حساب ندارید؟')
            ->assertSee(route('register'));
    }

    public function test_client_can_log_in_with_mobile_and_password(): void
    {
        $client = User::factory()->client()->create(['password' => 'secret-password']);

        $this->post('/login', [
            'mobile' => $client->mobile,
            'password' => 'secret-password',
        ])->assertRedirect(route('account.home'));

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
        ])->assertRedirect(route('account.home'));

        $this->assertAuthenticatedAs($client, 'client');
    }

    public function test_login_preserves_the_intended_public_account_url(): void
    {
        $client = User::factory()->client()->create(['password' => 'secret-password']);

        $this->get(route('account.orders.index'))->assertRedirect(route('login'));

        $this->post('/login', [
            'mobile' => $client->mobile,
            'password' => 'secret-password',
        ])->assertRedirect(route('account.orders.index'));

        $this->assertAuthenticatedAs($client, 'client');
    }

    public function test_intended_service_url_still_applies_service_authorization_after_login(): void
    {
        $client = User::factory()->client()->create(['password' => 'secret-password']);

        $this->get(route('account.services.index'))->assertRedirect(route('login'));

        $this->post('/login', [
            'mobile' => $client->mobile,
            'password' => 'secret-password',
        ])->assertRedirect(route('account.services.index'));

        $this->get(route('account.services.index'))->assertForbidden();
    }

    public function test_admin_cannot_access_client_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'client')
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    }

    public function test_admin_cannot_authenticate_through_client_login(): void
    {
        $admin = User::factory()->admin()->create(['password' => 'secret-password']);

        $this->post('/login', [
            'mobile' => $admin->mobile,
            'password' => 'secret-password',
        ])->assertSessionHasErrors('mobile');

        $this->assertGuest('client');
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

    public function test_client_logout_rotates_the_session_without_flushing_unrelated_state(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client, 'client')
            ->withSession(['public-account-state' => 'sensitive'])
            ->post('/logout')
            ->assertRedirect(route('login'))
            ->assertSessionHas('public-account-state', 'sensitive');

        $this->assertGuest('client');
    }

    public function test_client_logout_preserves_simultaneous_admin_authentication(): void
    {
        $admin = User::factory()->admin()->create();
        $publicUser = User::factory()->client()->create();

        $this->actingAs($admin, 'web')
            ->actingAs($publicUser, 'client')
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest('client');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get('/admin')->assertOk();
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
