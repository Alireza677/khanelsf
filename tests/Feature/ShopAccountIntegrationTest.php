<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CustomerMembershipManager;
use App\Services\OrderConfirmationUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShopAccountIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_user_id_is_nullable_and_historical_orders_remain_valid(): void
    {
        $column = collect(Schema::getColumns('orders'))->firstWhere('name', 'user_id');
        $hasUserIndex = collect(Schema::getIndexes('orders'))
            ->contains(fn (array $index): bool => $index['columns'] === ['user_id']);
        $order = $this->order(['user_id' => null]);

        $this->assertNotNull($column);
        $this->assertTrue($column['nullable']);
        $this->assertTrue($hasUserIndex);
        $this->assertNull($order->user);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => null]);
    }

    public function test_deleting_an_order_owner_preserves_the_snapshot_and_nulls_ownership(): void
    {
        $user = User::factory()->client()->create();
        $order = $this->order(['user_id' => $user->id, 'customer_name' => 'Preserved Snapshot']);

        $user->delete();
        $order->refresh();

        $this->assertNull($order->user_id);
        $this->assertSame('Preserved Snapshot', $order->customer_name);
    }

    public function test_authenticated_checkout_assigns_the_explicit_client_user_and_preserves_snapshots(): void
    {
        $user = User::factory()->client()->create([
            'name' => 'Account Name',
            'mobile' => '09121111111',
            'email' => 'account@example.com',
        ]);

        $response = $this->actingAs($user, 'client')->checkout([
            'customer_name' => 'Order Snapshot Name',
            'customer_phone' => '09122222222',
            'customer_email' => 'snapshot@example.com',
            'customer_address' => 'Snapshot address',
        ]);
        $order = Order::query()->sole();

        $this->assertSame($user->id, $order->user_id);
        $this->assertSame('Order Snapshot Name', $order->customer_name);
        $this->assertSame('09122222222', $order->customer_phone);
        $this->assertSame('snapshot@example.com', $order->customer_email);
        $this->assertSignedConfirmationRedirect($response, $order);
    }

    public function test_guest_and_web_admin_checkouts_create_null_owner_orders(): void
    {
        $response = $this->checkout();
        $guestOrder = Order::query()->sole();
        $this->assertNull($guestOrder->user_id);
        $this->assertSignedConfirmationRedirect($response, $guestOrder);

        Order::query()->delete();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'web')->checkout(['customer_name' => 'Admin browser guest order']);

        $this->assertNull(Order::query()->sole()->user_id);
    }

    public function test_guest_cannot_access_account_orders(): void
    {
        $this->get('/account/orders')->assertRedirect(route('login'));
    }

    public function test_public_user_sees_only_owned_orders_without_customer_membership(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $owned = $this->order(['user_id' => $owner->id, 'order_number' => 'ORD-OWNED']);
        $this->order(['user_id' => $other->id, 'order_number' => 'ORD-FOREIGN']);
        $this->order(['user_id' => null, 'order_number' => 'ORD-GUEST']);

        $this->actingAs($owner, 'client')
            ->get('/account/orders')
            ->assertOk()
            ->assertSee('ORD-OWNED')
            ->assertDontSee('ORD-FOREIGN')
            ->assertDontSee('ORD-GUEST');

        $this->actingAs($owner, 'client')
            ->get(route('account.orders.show', $owned))
            ->assertOk()
            ->assertSee('ORD-OWNED');
    }

    public function test_customer_membership_does_not_change_order_ownership(): void
    {
        $user = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($customer, $user, 'owner');
        $owned = $this->order(['user_id' => $user->id, 'order_number' => 'ORD-MEMBER-OWNED']);
        $foreign = $this->order(['user_id' => null, 'order_number' => 'ORD-MEMBER-GUEST']);

        $this->actingAs($user, 'client')
            ->get(route('account.orders.show', $owned))
            ->assertOk();
        $this->actingAs($user, 'client')
            ->get(route('account.orders.show', $foreign))
            ->assertNotFound();
    }

    public function test_public_user_cannot_open_another_users_order(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $order = $this->order(['user_id' => $owner->id]);

        $this->actingAs($other, 'client')
            ->get(route('account.orders.show', $order))
            ->assertNotFound();
    }

    public function test_guest_confirmation_requires_a_valid_signature_and_cannot_be_enumerated(): void
    {
        $order = $this->order(['user_id' => null, 'order_number' => 'ORD-SIGNED-GUEST']);
        $other = $this->order(['user_id' => null, 'order_number' => 'ORD-OTHER-GUEST']);
        $url = app(OrderConfirmationUrl::class)->temporary($order);

        $this->get($url)
            ->assertOk()
            ->assertSee('ORD-SIGNED-GUEST');
        $this->get(route('checkout.thank-you', $order))->assertForbidden();
        $this->get(str_replace("/{$order->id}?", "/{$other->id}?", $url))
            ->assertForbidden()
            ->assertDontSee('ORD-OTHER-GUEST');
    }

    public function test_owned_confirmation_requires_both_signature_and_client_owner(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();
        $order = $this->order(['user_id' => $owner->id, 'order_number' => 'ORD-OWNER-SIGNED']);
        $url = app(OrderConfirmationUrl::class)->temporary($order);

        $this->actingAs($owner, 'client')->get($url)->assertOk();
        $this->actingAs($other, 'client')->get($url)->assertNotFound();
    }

    public function test_account_and_header_always_offer_my_orders_to_public_users(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')
            ->get('/account')
            ->assertOk()
            ->assertSee('سفارش‌های من')
            ->assertSee('هنوز سفارشی ثبت نکرده‌اید.')
            ->assertSee(route('account.orders.index'), false);
    }

    public function test_session_cart_survives_public_login_and_registration(): void
    {
        $product = Product::factory()->published()->create(['title' => 'Persistent Cart Product']);
        $user = User::factory()->client()->create(['password' => 'secret-password']);

        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $this->post('/login', ['mobile' => $user->mobile, 'password' => 'secret-password']);
        $this->get(route('cart.index'))->assertSee('Persistent Cart Product');

        $this->post('/logout');
        $this->post('/register', [
            'name' => 'New Cart User',
            'mobile' => '09129999999',
            'email' => null,
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
        ]);
        $this->get(route('cart.index'))->assertSee('Persistent Cart Product');
    }

    private function checkout(array $overrides = [])
    {
        Mail::fake();
        $product = Product::factory()->published()->create(['price' => 50]);
        $this->post(route('cart.add', $product), ['quantity' => 1]);

        return $this->post(route('checkout.store'), array_replace([
            'customer_name' => 'Checkout Customer',
            'customer_phone' => '09120000000',
            'customer_email' => 'checkout@example.com',
        ], $overrides));
    }

    private function order(array $overrides = []): Order
    {
        return Order::query()->create(array_replace([
            'order_number' => 'ORD-'.fake()->unique()->numerify('######'),
            'customer_name' => 'Historical Snapshot',
            'customer_phone' => '09120000000',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'manual',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ], $overrides));
    }

    private function assertSignedConfirmationRedirect($response, Order $order): void
    {
        $location = $response->headers->get('Location');

        $response->assertRedirect();
        $this->assertStringStartsWith(route('checkout.thank-you', $order).'?expires=', $location);
        $this->assertStringContainsString('&signature=', $location);
        $this->get($location)->assertOk();
    }
}
