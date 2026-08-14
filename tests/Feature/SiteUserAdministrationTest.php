<?php

namespace Tests\Feature;

use App\Filament\Resources\SiteUserResource;
use App\Filament\Resources\SiteUserResource\Pages\ListSiteUsers;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\CreateCustomerForUser;
use App\Services\CustomerMembershipManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SiteUserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_only_shows_public_users_and_supports_search_counts_and_customer_filters(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'مدیر پنهان']);
        $withoutCustomer = User::factory()->client()->create(['name' => 'علی رضایی', 'mobile' => '09121112233']);
        $connected = User::factory()->client()->create(['name' => 'سارا احمدی', 'mobile' => '09351112233']);
        $customer = Customer::factory()->create(['display_name' => 'نبکاسازه']);
        app(CustomerMembershipManager::class)->attach($customer, $connected, 'member');
        Order::query()->create($this->orderAttributes($withoutCustomer));
        Order::query()->create($this->orderAttributes($withoutCustomer, 'ORD-SECOND'));

        $this->actingAs($admin);

        Livewire::test(ListSiteUsers::class)
            ->assertCanSeeTableRecords([$withoutCustomer, $connected])
            ->assertCanNotSeeTableRecords([$admin])
            ->searchTable('علی')
            ->assertCanSeeTableRecords([$withoutCustomer])
            ->assertCanNotSeeTableRecords([$connected])
            ->searchTable('09121112233')
            ->assertCanSeeTableRecords([$withoutCustomer]);

        Livewire::test(ListSiteUsers::class)
            ->filterTable('customer_status', 'without')
            ->assertCanSeeTableRecords([$withoutCustomer])
            ->assertCanNotSeeTableRecords([$connected]);

        $record = SiteUserResource::getEloquentQuery()->findOrFail($withoutCustomer->id);
        $this->assertSame(2, $record->orders_count);
        $this->get(SiteUserResource::getUrl('view', ['record' => $connected]))
            ->assertOk()->assertSee('نبکاسازه')->assertSee('عضو');
    }

    public function test_admin_connects_existing_customer_without_changing_user_or_orders_and_duplicate_fails_safely(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        $order = Order::query()->create($this->orderAttributes($user));

        $this->actingAs($admin);
        Livewire::test(ListSiteUsers::class)
            ->callTableAction('connectCustomer', $user, [
                'customer_id' => $customer->id,
                'membership_role' => 'owner',
                'is_primary' => true,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('customer_user', [
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'membership_role' => 'owner',
            'is_primary' => true,
        ]);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => $user->id]);

        $this->expectException(ValidationException::class);
        app(CustomerMembershipManager::class)->attach($customer, $user, 'member');
    }

    public function test_admin_creates_customer_and_connects_same_user_with_owner_primary_membership(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->client()->create(['name' => 'میلاد', 'mobile' => '09120000001', 'email' => 'milad@example.test']);
        $originalUserCount = User::query()->count();

        $this->actingAs($admin);
        Livewire::test(ListSiteUsers::class)
            ->callTableAction('createCustomerAndConnect', $user, [
                'display_name' => 'مشتری میلاد',
                'company_name' => 'شرکت میلاد',
                'mobile' => '09120000001',
                'email' => 'milad@example.test',
                'status' => Customer::STATUS_ACTIVE,
            ])
            ->assertHasNoTableActionErrors();

        $customer = Customer::query()->where('display_name', 'مشتری میلاد')->firstOrFail();
        $this->assertDatabaseHas('customer_user', [
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'membership_role' => 'owner',
            'is_primary' => true,
        ]);
        $this->assertSame($originalUserCount, User::query()->count());
        $this->assertSame('میلاد', $user->fresh()->name);
    }

    public function test_create_customer_and_connect_rolls_back_customer_when_membership_fails(): void
    {
        $inactive = User::factory()->client()->create(['status' => 'inactive']);

        try {
            app(CreateCustomerForUser::class)->handle($inactive, [
                'display_name' => 'نباید باقی بماند',
                'status' => Customer::STATUS_ACTIVE,
            ]);
            throw new RuntimeException('Expected validation failure.');
        } catch (ValidationException) {
            $this->assertDatabaseMissing('customers', ['display_name' => 'نباید باقی بماند']);
        }
    }

    public function test_public_user_cannot_access_site_user_admin_resource(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user)->get(SiteUserResource::getUrl())->assertForbidden();
    }

    private function orderAttributes(User $user, string $number = 'ORD-FIRST'): array
    {
        return [
            'user_id' => $user->id,
            'order_number' => $number,
            'customer_name' => $user->name,
            'customer_phone' => $user->mobile,
            'customer_email' => $user->email,
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1000,
            'total' => 1000,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ];
    }
}
