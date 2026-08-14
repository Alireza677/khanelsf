<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerMembershipManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccountLayoutConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_profile_requires_client_authentication(): void
    {
        $this->get(route('account.profile.edit'))->assertRedirect(route('login'));
    }

    public function test_public_user_can_open_canonical_profile_without_customer_concepts(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee('data-public-account-layout', false)
            ->assertSee('پروفایل من')
            ->assertDontSee('بدون حساب مشتری')
            ->assertDontSee('حساب مشتری')
            ->assertDontSee('تخصیص داده نشده');
    }

    public function test_legacy_profile_redirects_to_the_canonical_profile(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')
            ->get('/profile')
            ->assertRedirect(route('account.profile.edit'));
    }

    public function test_account_navigation_uses_the_canonical_profile_url(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')
            ->get(route('account.home'))
            ->assertOk()
            ->assertSee(route('account.profile.edit'), false)
            ->assertDontSee('href="'.route('client.profile.edit').'"', false);
    }

    public function test_account_home_profile_orders_and_services_share_the_account_layout(): void
    {
        $user = User::factory()->client()->create();

        foreach ([route('account.home'), route('account.profile.edit'), route('account.orders.index')] as $url) {
            $this->actingAs($user, 'client')->get($url)->assertOk()->assertSee('data-public-account-layout', false);
        }

        $customer = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($customer, $user, 'member');

        $this->actingAs($user, 'client')
            ->get(route('account.services.index', ['customer' => $customer->id]))
            ->assertOk()
            ->assertSee('data-public-account-layout', false);
    }

    public function test_service_navigation_remains_capability_aware_in_the_shared_layout(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->assertDontSee('خدمات و پروژه‌های من');

        $customer = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($customer, $user, 'member');

        $this->actingAs($user, 'client')
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->assertSee('خدمات و پروژه‌های من')
            ->assertSee(route('account.services.index'), false);
    }

    public function test_existing_profile_update_logic_is_reused_by_the_canonical_route(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')
            ->patch(route('account.profile.update'), [
                'name' => 'نام ویرایش‌شده',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect(route('account.profile.edit'));

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'نام ویرایش‌شده',
            'email' => 'updated@example.com',
        ]);
    }

    public function test_legacy_dashboard_remains_available(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')->get('/dashboard')->assertOk();
    }
}
