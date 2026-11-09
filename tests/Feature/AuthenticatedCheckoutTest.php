<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\CheckoutIdempotency;
use App\Services\OrderConfirmationUrl;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticatedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_guest_can_shop_but_neither_checkout_method_creates_financial_records(): void
    {
        $product = $this->cart();
        $this->get(route('shop.show', $product->slug))->assertOk();
        $this->get(route('cart.index'))->assertOk()->assertSee('data-checkout-auth', false);
        $this->get(route('checkout.create'))->assertRedirect(route('login', ['continue' => 'checkout']));
        $this->post(route('checkout.store'), $this->customer())->assertRedirect(route('login', ['continue' => 'checkout']));
        $this->postJson(route('checkout.store'), $this->customer())->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertDatabaseCount('checkout_intents', 0);
        $this->assertCount(1, session(CartService::SESSION_KEY));
        Http::assertNothingSent();
    }

    public function test_web_guard_alone_is_not_public_checkout_authentication(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'web');
        $this->cart();
        $this->get(route('checkout.create'))->assertRedirect(route('login', ['continue' => 'checkout']));
        $this->postJson(route('checkout.store'), $this->customer())->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertAuthenticatedAs($admin, 'web');
    }

    #[DataProvider('invalidClients')]
    public function test_invalid_client_cannot_create_an_order(array $attributes): void
    {
        $user = User::factory()->client()->create($attributes);
        $this->actingAs($user, 'client');
        $this->cart();
        $this->get(route('checkout.create'))->assertRedirect(route('login', ['continue' => 'checkout']));
        $this->actingAs($user, 'client')->postJson(route('checkout.store'), $this->customer())->assertUnauthorized();
        $this->assertGuest('client');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public static function invalidClients(): array
    {
        return ['inactive' => [['status' => 'inactive']], 'admin' => [['is_admin' => true]],
            'inactive admin' => [['is_admin' => true, 'status' => 'inactive']]];
    }

    public function test_page_login_and_registration_switch_return_to_fresh_checkout_without_javascript(): void
    {
        $product = $this->cart();
        $cart = session(CartService::SESSION_KEY);
        $this->get(route('checkout.create'))->assertRedirect(route('login', ['continue' => 'checkout']));
        $this->get(route('login', ['continue' => 'checkout']))->assertOk()
            ->assertSee('name="continue" value="checkout"', false)
            ->assertSee(route('register', ['continue' => 'checkout']), false);
        $this->get(route('register', ['continue' => 'checkout']))->assertOk();
        $this->post(route('client.register.store'), $this->registration())->assertRedirect(route('checkout.create'));
        $this->assertAuthenticated('client');
        $this->assertSame($cart, session(CartService::SESSION_KEY));
        $this->assertDatabaseCount('orders', 0);
        $page = $this->get(route('checkout.create'))->assertOk()->assertSee($product->title);
        $this->assertNotEmpty($page->viewData('cartReview'));
        $this->assertNotEmpty($page->viewData('checkoutIntent'));
        $this->assertDatabaseCount('customers', 0);
        $this->assertDatabaseCount('customer_user', 0);
    }

    public function test_modal_login_preserves_cart_admin_guard_and_reissues_owner_bound_review(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->client()->create(['password' => 'client-password']);
        $this->actingAs($admin, 'web');
        $this->cart();
        $cart = session(CartService::SESSION_KEY);
        $request = Request::create('/checkout');
        $request->setLaravelSession(session()->driver());
        $guestIntent = app(CheckoutIdempotency::class)->issue($request, null);
        $guestReview = app(CartService::class)->reviewToken(app(CartService::class)->refresh());
        $cart = session(CartService::SESSION_KEY);

        $this->postJson(route('client.login.store'), ['mobile' => $user->mobile, 'password' => 'client-password', 'continue' => 'checkout'])
            ->assertOk()->assertExactJson(['authenticated' => true, 'redirect_url' => '/checkout']);
        $this->assertSame($cart, session(CartService::SESSION_KEY));
        $this->assertAuthenticatedAs($user, 'client');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertDatabaseCount('orders', 0);
        $page = $this->get(route('checkout.create'))->assertOk();
        $this->assertNotSame($guestIntent, $page->viewData('checkoutIntent'));
        $this->assertNotSame($guestReview, $page->viewData('cartReview'));
        $this->post(route('checkout.store'), [...$this->customer(), '_checkout_intent' => $guestIntent, '_cart_review' => $guestReview])
            ->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_failed_modal_login_and_registration_keep_cart_and_return_field_errors(): void
    {
        $this->cart();
        $cart = session(CartService::SESSION_KEY);
        $user = User::factory()->client()->create(['password' => 'client-password']);
        $this->postJson(route('client.login.store'), ['mobile' => $user->mobile, 'password' => 'wrong', 'continue' => 'checkout'])
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');
        $this->postJson(route('client.register.store'), [...$this->registration(), 'mobile' => $user->mobile])
            ->assertUnprocessable()->assertJsonValidationErrors('mobile');
        $this->assertSame($cart, session(CartService::SESSION_KEY));
        $this->assertGuest('client');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_successful_modal_registration_cannot_set_role_or_destination_or_claim_legacy_order(): void
    {
        $legacy = $this->legacyOrder(['customer_phone' => '09129999999', 'customer_email' => 'new@example.com']);
        $this->cart();
        $cart = session(CartService::SESSION_KEY);
        $this->postJson(route('client.register.store'), [...$this->registration(), 'is_admin' => true, 'status' => 'inactive',
            'continue' => 'checkout', 'return_to' => 'https://evil.example', 'redirect_url' => 'https://evil.example'])
            ->assertOk()->assertJsonPath('redirect_url', '/checkout');
        $user = Auth::guard('client')->user();
        $this->assertTrue($user->isClient() && $user->isActive());
        $this->assertSame($cart, session(CartService::SESSION_KEY));
        $this->assertNull($legacy->fresh()->user_id);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('customers', 0);
        $this->get(route('account.orders.show', $legacy))->assertNotFound();
    }

    public function test_explicit_post_checkout_fallback_ignores_external_referer_and_returns_to_get_checkout(): void
    {
        $user = User::factory()->client()->create(['password' => 'client-password']);
        $this->cart();
        $redirect = $this->withHeader('Referer', 'https://evil.example')->post(route('checkout.store'), $this->customer());
        $redirect->assertRedirect(route('login', ['continue' => 'checkout']));
        $this->get($redirect->headers->get('Location'))->assertOk();
        $this->post(route('client.login.store'), ['mobile' => $user->mobile, 'password' => 'client-password'])
            ->assertRedirect(route('checkout.create'));
        $this->assertDatabaseCount('orders', 0);
    }

    #[DataProvider('unsafeDestinations')]
    public function test_login_never_redirects_to_an_unsafe_intended_destination(string $destination): void
    {
        $user = User::factory()->client()->create(['password' => 'client-password']);
        $this->withSession(['url.intended' => $destination])->postJson(route('client.login.store'), [
            'mobile' => $user->mobile, 'password' => 'client-password', 'return_to' => $destination,
        ])->assertOk()->assertJsonPath('redirect_url', route('account.home'));
    }

    public static function unsafeDestinations(): array
    {
        return [['https://evil.example/checkout'], ['//evil.example/checkout'], ['/\\evil.example/checkout'],
            ['javascript:alert(1)'], ['https://localhost.evil.example/account'], ['/admin']];
    }

    public function test_replayed_auth_post_in_an_already_authenticated_tab_keeps_the_current_identity(): void
    {
        $owner = User::factory()->client()->create();
        $this->actingAs($owner, 'client');
        $this->postJson(route('client.register.store'), [...$this->registration(), 'continue' => 'checkout'])
            ->assertOk()->assertJsonPath('redirect_url', '/checkout');
        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($owner, 'client');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_final_order_uses_current_identity_and_editable_snapshot_and_appears_in_account(): void
    {
        $owner = User::factory()->client()->create(['name' => 'Account Owner', 'mobile' => '09121111111', 'email' => 'owner@example.com']);
        $other = User::factory()->client()->create();
        $this->actingAs($owner, 'client');
        $this->cart();
        $page = $this->get(route('checkout.create'))->assertOk()
            ->assertSee('value="Account Owner"', false)->assertSee('value="09121111111"', false)->assertSee('value="owner@example.com"', false);
        $payload = [...$this->customer(), 'user_id' => $other->id, '_cart_review' => $page->viewData('cartReview'), '_checkout_intent' => $page->viewData('checkoutIntent')];
        $first = $this->post(route('checkout.store'), $payload)->assertRedirect();
        $this->post(route('checkout.store'), $payload)->assertRedirect($first->headers->get('Location'));
        $order = Order::query()->sole();
        $this->assertSame($owner->id, $order->user_id);
        $this->assertSame('Snapshot Recipient', $order->customer_name);
        $this->assertSame('09123334444', $order->customer_phone);
        $this->assertSame('unpaid', $order->payment_status);
        $this->get(route('account.orders.index'))->assertOk()->assertSee($order->displayCode());
        $this->get(route('account.orders.show', $order))->assertOk()->assertSee('Snapshot Recipient')->assertSee('پرداخت‌نشده');
        $this->actingAs($other, 'client')->get(route('account.orders.show', $order))->assertNotFound();
        $this->get($first->headers->get('Location'))->assertNotFound();
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_leaked_private_receipt_and_payment_action_urls_require_a_valid_active_owner(): void
    {
        $owner = User::factory()->client()->create();
        $order = $this->legacyOrder(['user_id' => $owner->id, 'payment_method' => 'zarinpal']);
        $urls = app(OrderConfirmationUrl::class);
        $receipt = $urls->temporary($order);
        $this->get($receipt)->assertNotFound()->assertDontSee($order->customer_phone);
        foreach (['retry', 'reconcile'] as $action) {
            $this->post($urls->paymentAction($order, $action))->assertNotFound();
        }
        $this->actingAs($owner, 'client')->get($receipt)->assertOk()->assertSee($order->customer_phone);
        $owner->update(['status' => 'inactive']);
        $this->get($receipt)->assertNotFound();
        foreach (['retry', 'reconcile'] as $action) {
            $this->post($urls->paymentAction($order, $action))->assertNotFound();
        }
        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_legacy_and_deleted_owner_receipts_never_expose_private_snapshots(): void
    {
        $owner = User::factory()->client()->create();
        $legacy = $this->legacyOrder();
        $owned = $this->legacyOrder(['user_id' => $owner->id]);
        $urls = app(OrderConfirmationUrl::class);
        $ownedUrl = $urls->temporary($owned);
        $owner->delete();
        foreach ([$urls->temporary($legacy), $ownedUrl] as $url) {
            $this->get($url)->assertOk()->assertSee('پیگیری سفارش قدیمی')
                ->assertDontSee('Snapshot Recipient')->assertDontSee('09123334444')
                ->assertDontSee('private@example.com')->assertDontSee('Private address')->assertDontSee('Private notes');
        }
        $this->assertNull($owned->fresh()->user_id);
        $this->get(route('checkout.thank-you', $legacy))->assertForbidden();
    }

    public function test_modal_csrf_expiration_has_a_safe_json_contract_without_authenticating(): void
    {
        $this->cart();
        $this->app->bind(ValidateCsrfToken::class, EnforceClientAuthCsrf::class);
        $this->withSession(['_token' => 'expected-token'])->postJson(route('client.register.store'), $this->registration())
            ->assertStatus(419)->assertJsonPath('code', 'session_expired');
        $this->assertGuest('client');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertCount(1, session(CartService::SESSION_KEY));
    }

    public function test_modal_rate_limit_preserves_cart_and_retry_after_header(): void
    {
        $this->cart();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson(route('client.login.store'), ['mobile' => '09129999999', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson(route('client.login.store'), ['mobile' => '09129999999', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After')->assertJsonPath('code', 'rate_limited');
        $this->assertGuest('client');
        $this->assertCount(1, session(CartService::SESSION_KEY));
        $this->assertDatabaseCount('orders', 0);
    }

    private function cart(): Product
    {
        $product = Product::factory()->published()->create(['title' => 'Persistent checkout product', 'price' => '100.00', 'sale_price' => null]);
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect();

        return $product;
    }

    private function registration(): array
    {
        return ['name' => 'New Public User', 'mobile' => '09129999999', 'email' => 'new@example.com',
            'password' => 'secure-password', 'password_confirmation' => 'secure-password'];
    }

    private function customer(): array
    {
        return ['customer_name' => 'Snapshot Recipient', 'customer_phone' => '09123334444'];
    }

    private function legacyOrder(array $attributes = []): Order
    {
        return Order::query()->create([...$this->customer(), 'order_number' => 'LEGACY-'.Str::uuid(),
            'customer_email' => 'private@example.com', 'customer_address' => 'Private address', 'notes' => 'Private notes',
            'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'manual',
            'subtotal' => '100.00', 'total' => '100.00', ...$attributes]);
    }
}

class EnforceClientAuthCsrf extends ValidateCsrfToken
{
    protected function runningUnitTests()
    {
        return false;
    }
}
