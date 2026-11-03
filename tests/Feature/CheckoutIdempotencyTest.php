<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CheckoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_double_submission_converges_after_cart_and_review_tokens_are_cleared(): void
    {
        $form = $this->form();
        $first = $this->submit($form)->assertRedirect();
        $order = Order::query()->sole();
        $this->submit($form)->assertRedirect($first->headers->get('Location'));
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertSame('0.30', $order->total);
        $this->assertNull($order->user_id);
        $this->get($first->headers->get('Location'))->assertOk()->assertSee('سفارش دریافت شد');
        $this->assertDatabaseHas('checkout_intents', ['order_id' => $order->id]);
        Http::assertNothingSent();
    }

    public function test_changed_customer_payload_cannot_reuse_committed_intent(): void
    {
        $form = $this->form();
        $this->submit($form);
        $this->submit($form, ['customer_phone' => '09129999999'])->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_replay_with_precommit_session_snapshot_clears_only_the_consumed_cart(): void
    {
        $form = $this->form();
        $beforeCommit = session()->all();
        $this->submit($form)->assertRedirect();
        // Another request may already have loaded this Session before the first commit.
        $this->withSession($beforeCommit);
        $this->submit($form)->assertRedirect();
        $this->assertTrue(app(CartService::class)->isEmpty());
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_forged_key_and_session_loss_cannot_retrieve_order(): void
    {
        $form = $this->form();
        $this->submit($form);
        $this->submit([...$form, '_checkout_intent' => str_repeat('a', 64)])->assertSessionHasErrors('checkout');
        session()->invalidate();
        $this->submit($form)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_customer_switch_cannot_replay_an_owned_or_guest_order(): void
    {
        $user = User::factory()->client()->create();
        $this->actingAs($user, 'client');
        $form = $this->form();
        $this->submit($form, ['user_id' => 9999]);
        $this->assertSame($user->id, Order::query()->sole()->user_id);
        $this->actingAs(User::factory()->client()->create(), 'client');
        $this->submit($form)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_retry_after_rollback_does_not_leave_partial_order_or_consume_intent(): void
    {
        $form = $this->form();
        $fail = true;
        OrderItem::creating(function () use (&$fail): void {
            if ($fail) {
                throw ValidationException::withMessages(['cart' => 'Injected pre-commit failure']);
            }
        });
        $this->submit($form)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertNull(DB::table('checkout_intents')->sole()->order_id);
        $this->assertFalse(app(CartService::class)->isEmpty());
        $fail = false;
        $this->submit($form)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_price_review_failure_does_not_consume_intent_and_new_review_can_use_it(): void
    {
        $product = Product::factory()->published()->create(['price' => '1.00']);
        $form = $this->form($product);
        $product->update(['price' => '2.25']);
        $this->submit($form)->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $new = $this->get(route('checkout.create'))->assertOk();
        $this->assertSame($form['_checkout_intent'], $new->viewData('checkoutIntent'));
        $this->submit($form)->assertSessionHasErrors('cart');
        $this->submit([...$form, '_cart_review' => $new->viewData('cartReview')])->assertRedirect();
        $this->assertSame('6.75', Order::query()->sole()->total);
    }

    public function test_changed_cart_cannot_silently_rebind_an_uncommitted_form(): void
    {
        $product = Product::factory()->published()->create(['price' => '1.00']);
        $form = $this->form($product);
        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $this->submit($form)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_new_intent_allows_an_intentional_new_purchase_and_old_retry_does_not_clear_it(): void
    {
        $product = Product::factory()->published()->create(['price' => '1.00']);
        $first = $this->form($product);
        $this->submit($first);
        $second = $this->form($product);
        $this->assertNotSame($first['_checkout_intent'], $second['_checkout_intent']);
        $this->submit($first)->assertRedirect();
        $this->assertFalse(app(CartService::class)->isEmpty());
        $this->submit($second)->assertRedirect();
        $this->assertDatabaseCount('orders', 2);
        $this->assertDatabaseCount('order_items', 2);
    }

    public function test_duplicate_submission_does_not_resend_order_received_emails(): void
    {
        Setting::query()->create(['key' => 'shop_order_admin_email', 'value' => 'admin@example.com', 'group' => 'shop', 'type' => 'text']);
        $form = $this->form();
        // Existing raw mail path: count real dispatch calls, not Mailable-only fake assertions.
        $calls = 0;
        Mail::shouldReceive('raw')->andReturnUsing(function (string $body) use (&$calls): void {
            $calls++;
            $this->assertStringContainsString('unpaid', $body);
            $this->assertStringNotContainsString('پرداخت موفق', $body);
        });
        $this->submit($form, ['customer_email' => 'buyer@example.com']);
        $this->submit($form, ['customer_email' => 'buyer@example.com']);
        $this->assertSame(2, $calls);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_database_rejects_a_duplicate_checkout_key(): void
    {
        $this->form();
        $row = (array) DB::table('checkout_intents')->sole();
        unset($row['id']);
        $this->expectException(QueryException::class);
        DB::table('checkout_intents')->insert($row);
    }

    public function test_expired_pending_intent_requires_new_checkout_form(): void
    {
        $form = $this->form();
        $this->travel(2)->days();
        $this->submit($form)->assertSessionHasErrors('checkout');
        $this->assertDatabaseCount('orders', 0);
        $new = $this->get(route('checkout.create'))->assertOk();
        $this->assertNotSame($form['_checkout_intent'], $new->viewData('checkoutIntent'));
    }

    private function form(?Product $product = null): array
    {
        $product ??= Product::factory()->published()->create(['price' => '0.10', 'sale_price' => null]);
        $this->post(route('cart.add', $product), ['quantity' => 3])->assertRedirect();
        $page = $this->get(route('checkout.create'))->assertOk();

        return ['_cart_review' => $page->viewData('cartReview'), '_checkout_intent' => $page->viewData('checkoutIntent')];
    }

    private function submit(array $form, array $extra = [])
    {
        return $this->post(route('checkout.store'), [
            'customer_name' => 'Idempotent customer', 'customer_phone' => '09120000000', ...$form, ...$extra,
        ]);
    }
}
