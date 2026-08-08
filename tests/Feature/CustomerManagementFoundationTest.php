<?php

namespace Tests\Feature;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Models\Customer;
use App\Models\User;
use App\Services\ClientCustomerResolver;
use App\Services\CustomerMembershipManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerManagementFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_customer_in_filament(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'display_name' => 'مشتری آزمایشی',
                'company_name' => 'شرکت آزمایشی',
                'status' => Customer::STATUS_ACTIVE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', ['display_name' => 'مشتری آزمایشی']);

        $customer = Customer::query()->where('display_name', 'مشتری آزمایشی')->firstOrFail();
        $this->get(CustomerResource::getUrl('view', ['record' => $customer]))->assertOk();
    }

    public function test_membership_manager_assigns_clients_and_rejects_admins(): void
    {
        $customer = Customer::factory()->create();
        $client = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();
        $memberships = app(CustomerMembershipManager::class);

        $memberships->assign($customer, $client, 'owner', true);

        $this->assertDatabaseHas('customer_user', [
            'customer_id' => $customer->id,
            'user_id' => $client->id,
            'membership_role' => 'owner',
            'is_primary' => true,
        ]);

        $this->expectException(ValidationException::class);
        $memberships->assign($customer, $admin, 'member');
    }

    public function test_assigned_client_sees_only_accessible_customer(): void
    {
        $client = User::factory()->client()->create();
        $assigned = Customer::factory()->create(['display_name' => 'Assigned Account']);
        $other = Customer::factory()->create(['display_name' => 'Other Account']);
        app(CustomerMembershipManager::class)->assign($assigned, $client, 'member');

        $this->actingAs($client, 'client')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Assigned Account')
            ->assertSee('پروژه‌های فعال')
            ->assertSee('دسترسی سریع')
            ->assertDontSee('Other Account');

        $this->actingAs($client, 'client')
            ->get('/dashboard?customer='.$other->id)
            ->assertForbidden();
    }

    public function test_multiple_customer_selector_and_placeholder_pages_preserve_authorized_context(): void
    {
        $client = User::factory()->client()->create();
        $first = Customer::factory()->create(['display_name' => 'First Account']);
        $second = Customer::factory()->create(['display_name' => 'Second Account']);
        $memberships = app(CustomerMembershipManager::class);
        $memberships->assign($first, $client, 'owner');
        $memberships->assign($second, $client, 'member');

        $this->actingAs($client, 'client')
            ->get('/dashboard?customer='.$second->id)
            ->assertOk()
            ->assertSee('First Account')
            ->assertSee('Second Account')
            ->assertSee('dashboard/projects?customer='.$second->id, false);

        $this->actingAs($client, 'client')
            ->get('/dashboard/projects?customer='.$second->id)
            ->assertOk()
            ->assertSee('هنوز پروژه‌ای برای شما ثبت نشده است.');
    }

    public function test_placeholder_pages_are_protected_by_client_authentication(): void
    {
        $this->get('/dashboard/reports')->assertRedirect(route('login'));
    }

    public function test_unassigned_client_receives_controlled_empty_state(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client, 'client')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('حساب مشتری در دسترس نیست');
    }

    public function test_removing_membership_removes_access_immediately(): void
    {
        $client = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        $memberships = app(CustomerMembershipManager::class);
        $memberships->assign($customer, $client, 'member');

        $this->assertTrue(app(ClientCustomerResolver::class)->resolve($client)->is($customer));

        $memberships->remove($customer, $client);

        $this->assertNull(app(ClientCustomerResolver::class)->resolve($client));
    }

    public function test_inactive_and_archived_customers_fail_closed(): void
    {
        $client = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($customer, $client, 'member');

        foreach ([Customer::STATUS_INACTIVE, Customer::STATUS_ARCHIVED] as $status) {
            $customer->update(['status' => $status]);

            $this->assertNull(app(ClientCustomerResolver::class)->resolve($client));
        }
    }

    public function test_deleting_customer_or_user_does_not_delete_the_related_entity(): void
    {
        $user = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($customer, $user, 'member');

        $customer->delete();
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('customer_user', ['user_id' => $user->id]);

        $secondCustomer = Customer::factory()->create();
        $secondUser = User::factory()->client()->create();
        app(CustomerMembershipManager::class)->assign($secondCustomer, $secondUser, 'member');
        $secondUser->delete();

        $this->assertDatabaseHas('customers', ['id' => $secondCustomer->id]);
    }

    public function test_explicit_foreign_customer_resolution_is_denied(): void
    {
        $client = User::factory()->client()->create();
        $foreignCustomer = Customer::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(ClientCustomerResolver::class)->resolve($client, $foreignCustomer->id);
    }
}
