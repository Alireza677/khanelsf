<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Services\ZarinpalGraphqlClient;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ZarinpalPaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_payment_checkout_still_works(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create([
            'price' => 25,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Manual Customer',
            'customer_phone' => '+1 555 000 1000',
        ]);

        $order = Order::query()->first();

        $response->assertRedirect();
        $this->assertStringStartsWith(route('checkout.thank-you', $order).'?expires=', $response->headers->get('Location'));

        $this->assertDatabaseHas('orders', [
            'customer_name' => 'Manual Customer',
            'payment_method' => 'manual',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ]);
    }

    public function test_zarinpal_without_token_does_not_crash_or_clear_cart(): void
    {
        Setting::query()->create([
            'key' => 'payment_gateway',
            'value' => 'zarinpal',
            'group' => 'payment',
            'type' => 'select',
        ]);

        $product = Product::factory()->published()->create([
            'title' => 'Zarinpal Product',
            'price' => 80,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1]);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Zarinpal Customer',
            'customer_phone' => '+1 555 000 2000',
        ])
            ->assertSessionHasErrors(['payment']);

        $this->assertDatabaseCount('orders', 0);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Zarinpal Product');
    }

    public function test_zarinpal_errors_do_not_leak_token(): void
    {
        $token = 'secret-zarinpal-token';

        Setting::query()->create([
            'key' => 'zarinpal_access_token',
            'value' => $token,
            'group' => 'payment',
            'type' => 'password',
        ]);

        Http::fake([
            '*' => Http::response(['message' => 'upstream failed'], 500),
        ]);

        $result = app(ZarinpalGraphqlClient::class)->post('query Test { test }');

        $this->assertFalse($result['ok']);
        $this->assertStringNotContainsString($token, json_encode($result));
    }

    public function test_zarinpal_callback_route_loads_safely(): void
    {
        $this->get(route('payments.zarinpal.callback', [
            'Authority' => 'A000000',
            'Status' => 'NOK',
        ]))
            ->assertOk()
            ->assertSee('نتیجه پرداخت')
            ->assertSee('سفارش برای تایید پرداخت پیدا نشد.');
    }

    public function test_zarinpal_placeholder_methods_are_safe(): void
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-ZARINPAL',
            'customer_name' => 'Zarinpal Placeholder',
            'customer_phone' => '+1 555 000 3000',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'zarinpal',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ]);

        $gateway = app(ZarinpalPaymentGateway::class);

        $this->assertFalse($gateway->createPayment($order)['ok']);
        $this->assertFalse($gateway->verifyPayment($order)['verified']);
    }
}
