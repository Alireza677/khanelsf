<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_page_is_accessible_to_a_guest(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('ایجاد حساب کاربری')
            ->assertSee('قبلاً حساب دارید؟')
            ->assertSee('ورود');
    }

    public function test_authenticated_public_user_cannot_open_registration_page(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')
            ->get('/register')
            ->assertRedirect('/');
    }

    public function test_successful_registration_creates_and_authenticates_an_active_non_admin_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'کاربر عمومی',
            'mobile' => '09123456789',
            'email' => 'public@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'is_admin' => true,
            'status' => 'inactive',
        ]);

        $user = User::query()->where('mobile', '09123456789')->sole();

        $response->assertRedirect(route('account.home'));
        $this->assertAuthenticatedAs($user, 'client');
        $this->assertFalse($user->is_admin);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('secure-password', $user->password));
        $this->assertCount(0, $user->customers);
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_user', 0);
    }

    public function test_registration_normalizes_an_international_iranian_mobile(): void
    {
        $this->post('/register', $this->registrationData([
            'mobile' => '+98 912 345 6789',
        ]))->assertRedirect(route('account.home'));

        $this->assertDatabaseHas('users', ['mobile' => '09123456789']);
    }

    public function test_registration_normalizes_persian_digits(): void
    {
        $this->post('/register', $this->registrationData([
            'mobile' => '۰۹۱۲-۳۴۵-۶۷۸۹',
        ]))->assertRedirect(route('account.home'));

        $this->assertDatabaseHas('users', ['mobile' => '09123456789']);
    }

    public function test_duplicate_normalized_mobile_is_rejected(): void
    {
        User::factory()->client()->create(['mobile' => '09123456789']);

        $this->post('/register', $this->registrationData([
            'mobile' => '+98 912 345 6789',
        ]))->assertSessionHasErrors('mobile');

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest('client');
    }

    public function test_password_confirmation_is_required_to_match(): void
    {
        $this->post('/register', $this->registrationData([
            'password_confirmation' => 'different-password',
        ]))->assertSessionHasErrors('password');

        $this->assertDatabaseCount('users', 0);
    }

    private function registrationData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'کاربر جدید',
            'mobile' => '09123456789',
            'email' => null,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ], $overrides);
    }
}
