<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Services\OrderConfirmationUrl;
use App\Services\ZarinpalConfiguration;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Opt-in: actual Sandbox only. No production merchant, bank charge or external callback request. */
class ZarinpalSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('KING_ZARINPAL_SANDBOX_TESTS') !== '1') {
            $this->markTestSkipped('Actual Sandbox tests require KING_ZARINPAL_SANDBOX_TESTS=1.');
        }
        URL::forceRootUrl('https://example.com');
        URL::forceScheme('https');
        config(['payments.zarinpal.enabled' => true, 'payments.zarinpal.environment' => 'sandbox',
            'payments.zarinpal.sandbox_merchant_id' => (string) Str::uuid(), 'payments.zarinpal.production_enabled' => false]);
        Mail::fake();
    }

    public function test_actual_sandbox_irt_amount_callback_verification_and_duplicate_contract(): void
    {
        [$order, $attempt] = $this->start();
        $callback = $this->sandboxAction($attempt, 'OK');
        $wrong = $this->sandboxHttp()->post('https://sandbox.zarinpal.com/pg/v4/payment/verify.json', [
            'merchant_id' => app(ZarinpalConfiguration::class)->merchantId(), 'authority' => $attempt->authority, 'amount' => 100000,
        ]);
        $this->assertSame(-50, $wrong->json('errors.code'), 'Sandbox must reject the incorrect tenfold verification amount.');
        $this->get($callback)->assertRedirect();
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('gateway_verified', $attempt->refresh()->source);
        $this->assertSame('sandbox', $attempt->environment);
        $this->assertNotEmpty($attempt->provider_reference);
        $this->assertNotNull($attempt->verified_at);
        $duplicate = $this->sandboxHttp()->post('https://sandbox.zarinpal.com/pg/v4/payment/verify.json', [
            'merchant_id' => app(ZarinpalConfiguration::class)->merchantId(), 'authority' => $attempt->authority, 'amount' => 10000,
        ]);
        $this->assertSame(101, $duplicate->json('data.code'));
        $this->assertSame($attempt->provider_reference, (string) $duplicate->json('data.ref_id'));
        $inquiry = $this->sandboxHttp()->post('https://sandbox.zarinpal.com/pg/v4/payment/inquiry.json', [
            'merchant_id' => app(ZarinpalConfiguration::class)->merchantId(), 'authority' => $attempt->authority,
        ]);
        $this->assertSame('VERIFIED', $inquiry->json('data.status'));
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_actual_sandbox_missing_callback_is_recovered_through_inquiry_then_verify(): void
    {
        [$order, $attempt] = $this->start();
        $this->sandboxAction($attempt, 'OK'); // Deliberately do not invoke King's callback.
        $this->assertFalse($order->refresh()->isPaid());
        app(ZarinpalPaymentGateway::class)->reconcilePayment($order);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('succeeded', $attempt->refresh()->status);
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_actual_sandbox_cancel_does_not_release_an_in_bank_attempt_or_create_another_charge(): void
    {
        [$order, $attempt] = $this->start();
        $callback = $this->sandboxAction($attempt, 'NOK');
        $this->get($callback)->assertRedirect();
        $this->assertFalse($order->refresh()->isPaid());
        // Current Sandbox keeps IN_BANK after its NOK simulator link. Do not infer failure.
        $this->assertSame('pending', $attempt->refresh()->status);
        $this->assertSame($order->id, $attempt->active_order_id);
        $result = app(ZarinpalPaymentGateway::class)->createPayment($order, 'retry-'.$attempt->id);
        $this->assertTrue($result['ok'], 'Sandbox retry initiation did not succeed.');
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame($attempt->id, $result['attempt_id']);
        $this->assertSame($order->id, PaymentAttempt::query()->findOrFail($result['attempt_id'])->order_id);
    }

    private function start(): array
    {
        $order = Order::query()->create(['order_number' => 'SANDBOX-'.Str::uuid(), 'customer_name' => 'Sandbox fixture',
            'customer_phone' => '09120000000', 'status' => 'pending', 'payment_status' => 'unpaid',
            'payment_method' => 'zarinpal', 'subtotal' => '10000.00', 'total' => '10000.00']);
        app(OrderConfirmationUrl::class)->remember($order);
        $result = app(ZarinpalPaymentGateway::class)->createPayment($order);
        $this->assertTrue($result['ok'], 'Actual Sandbox initiation unavailable; safe diagnostic: '.json_encode(PaymentAttempt::query()->first()?->diagnostics));
        $attempt = PaymentAttempt::query()->findOrFail($result['attempt_id']);
        $this->assertSame('10000', $attempt->gateway_amount);
        $this->assertSame('IRT', $attempt->gateway_amount_unit);

        return [$order, $attempt];
    }

    private function sandboxAction(PaymentAttempt $attempt, string $status): string
    {
        $page = $this->sandboxHttp()->get('https://sandbox.zarinpal.com/pg/StartPay/'.$attempt->authority);
        $this->assertSame(200, $page->status(), 'Sandbox payment page was unavailable.');
        $pattern = '~href="(https?://sandbox\.zarinpal\.com/pg/verify/[A-Za-z0-9]+/\?status='.$status.')"~';
        $matched = preg_match($pattern, $page->body(), $matches);
        $this->assertSame(1, $matched, 'The official Sandbox simulator action was not found.');
        $action = preg_replace('~^http:~', 'https:', html_entity_decode($matches[1]));
        $response = $this->sandboxHttp()->get($action);
        $this->assertSame(302, $response->status(), 'Sandbox simulator did not return a callback redirect.');
        $callback = $response->header('Location');
        $url = parse_url($callback);
        $this->assertSame('example.com', $url['host'] ?? null);
        $this->assertSame('/payments/zarinpal/callback', $url['path'] ?? null);
        parse_str($url['query'] ?? '', $query);
        $this->assertSame($attempt->authority, $query['Authority'] ?? null);
        $this->assertSame($status, $query['Status'] ?? null);

        return $callback;
    }

    private function sandboxHttp(): PendingRequest
    {
        return Http::asJson()->acceptJson()->connectTimeout(5)->timeout(18)->withOptions(['allow_redirects' => false, 'verify' => true]);
    }
}
