<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteSettings;
use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderConfirmationUrl;
use App\Services\OrderPaymentService;
use App\Services\SettingsService;
use App\Services\ZarinpalConfiguration;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ZarinpalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        URL::forceRootUrl('https://king.test');
        URL::forceScheme('https');
        config(['payments.zarinpal.enabled' => true, 'payments.zarinpal.environment' => 'sandbox',
            'payments.zarinpal.sandbox_merchant_id' => (string) Str::uuid(), 'payments.zarinpal.production_enabled' => false]);
        Mail::fake();
        Http::preventStrayRequests();
    }

    public function test_initiation_persists_exact_bound_snapshot_before_http_and_reuses_redirect(): void
    {
        $order = $this->order();
        $baseline = DB::transactionLevel();
        $merchant = config('payments.zarinpal.sandbox_merchant_id');
        $this->fakeHttp(function ($request) use ($order, $baseline, $merchant) {
            $this->assertSame($baseline, DB::transactionLevel());
            $attempt = PaymentAttempt::query()->sole();
            $this->assertNotNull($attempt->requested_at);
            $this->assertSame('initiate', $attempt->operation);
            $this->assertSame($order->id, $attempt->active_order_id);
            $this->assertSame('10000.00', $attempt->amount);
            $this->assertSame('10000', $attempt->gateway_amount);
            $this->assertSame('IRT', $attempt->gateway_amount_unit);
            $this->assertSame('sandbox', $attempt->environment);
            $this->assertSame(10000, $request['amount']);
            $this->assertSame('IRT', $request['currency']);
            $this->assertSame($merchant, $request['merchant_id']);
            $this->assertStringNotContainsString($merchant, json_encode($attempt->getAttributes()));
            $this->assertStringStartsWith('https://king.test/payments/zarinpal/callback?ctx=', $request['callback_url']);

            return Http::response(['data' => ['code' => 100, 'authority' => $this->authority(), 'redirect_url' => 'https://evil.test'], 'errors' => []]);
        });
        $gateway = app(ZarinpalPaymentGateway::class);
        $first = $gateway->createPayment($order);
        $second = $gateway->createPayment($order);
        $this->assertSame('https://sandbox.zarinpal.com/pg/StartPay/'.$this->authority(), $first['redirect_url']);
        $this->assertSame($first['redirect_url'], $second['redirect_url']);
        $this->assertSame($first['attempt_id'], $second['attempt_id']);
        $this->assertFalse($order->refresh()->isPaid());
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertSentCount(1);
    }

    #[DataProvider('invalidInitiations')]
    public function test_untrusted_provider_initiation_never_becomes_a_payment(array|string $body, int $http): void
    {
        $this->fakeHttp(['*' => Http::response($body, $http)]);
        $order = $this->order();
        $result = app(ZarinpalPaymentGateway::class)->createPayment($order);
        $this->assertNull($result['redirect_url']);
        $this->assertSame('uncertain', PaymentAttempt::query()->sole()->status);
        $this->assertNotNull(PaymentAttempt::query()->sole()->active_order_id);
        $this->assertFalse($order->refresh()->isPaid());
        app(ZarinpalPaymentGateway::class)->createPayment($order);
        Http::assertSentCount(1);
    }

    public static function invalidInitiations(): array
    {
        return [
            'missing authority' => [['data' => ['code' => 100], 'errors' => []], 200],
            'string code' => [['data' => ['code' => '100', 'authority' => 'S'.str_repeat('a', 35)], 'errors' => []], 200],
            'wrong environment' => [['data' => ['code' => 100, 'authority' => 'A'.str_repeat('a', 35)], 'errors' => []], 200],
            'business errors with data' => [['data' => ['code' => 100, 'authority' => 'S'.str_repeat('a', 35)], 'errors' => ['bad']], 200],
            'contradictory rejection' => [['data' => ['code' => 100, 'authority' => 'S'.str_repeat('a', 35)], 'errors' => ['code' => -9]], 200],
            'unexpected status' => [['data' => ['code' => 100, 'authority' => 'S'.str_repeat('a', 35)], 'errors' => []], 302],
            'malformed json' => ['{broken', 200], 'oversized response' => [str_repeat('x', 70000), 200],
        ];
    }

    public function test_confirmed_initiation_failure_allows_only_an_explicit_new_intent(): void
    {
        $this->fakeSequence()->push(['data' => [], 'errors' => ['code' => -9]], 422)
            ->push(['data' => ['code' => 100, 'authority' => $this->authority()], 'errors' => []]);
        $order = $this->order();
        $gateway = app(ZarinpalPaymentGateway::class);
        $gateway->createPayment($order);
        $first = PaymentAttempt::query()->sole();
        $this->assertSame('failed', $first->status);
        $this->assertNull($first->active_order_id);
        $gateway->createPayment($order);
        Http::assertSentCount(1);
        $result = $gateway->createPayment($order, 'retry-'.$first->id);
        $this->assertNotNull($result['redirect_url']);
        $this->assertDatabaseCount('payment_attempts', 2);
        Http::assertSentCount(2);
    }

    public function test_network_timeout_and_crashed_initiation_never_resend_the_request(): void
    {
        $this->fakeHttp(['*' => Http::failedConnection()]);
        $order = $this->order();
        $gateway = app(ZarinpalPaymentGateway::class);
        $gateway->createPayment($order);
        $attempt = PaymentAttempt::query()->sole();
        $this->assertSame('uncertain', $attempt->status);
        $this->assertNotNull($attempt->requested_at);
        $this->assertNull($attempt->authority);
        $gateway->createPayment($order, 'retry-'.$attempt->id);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertNotNull($attempt->active_order_id);
        $second = $this->order();
        $prepared = app(OrderPaymentService::class)->prepareGateway($second, 'initial', app(ZarinpalConfiguration::class));
        app(OrderPaymentService::class)->initiateGateway($prepared);
        $this->travel(91)->seconds();
        $gateway->createPayment($second);
        $this->assertSame('uncertain', $prepared->refresh()->status);
        $this->assertNull($prepared->authority);
        $this->assertSame('initiation_unknown', $prepared->diagnostics['code']);
    }

    #[DataProvider('verificationCodes')]
    public function test_real_verification_contract_settles_once_and_preserves_fulfillment(int $code): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        app(OrderPaymentService::class)->complete($order, User::factory()->admin()->create());
        $baseline = DB::transactionLevel();
        $this->fakeHttp(function ($request) use ($baseline, $code, $attempt) {
            $this->assertSame($baseline, DB::transactionLevel());
            $this->assertStringEndsWith('/verify.json', $request->url());
            $this->assertSame(10000, $request['amount']);
            $this->assertSame($attempt->authority, $request['authority']);

            return Http::response(['data' => ['code' => $code, 'ref_id' => 123456], 'errors' => []]);
        });
        $gateway = app(ZarinpalPaymentGateway::class);
        $first = $gateway->verifyPayment($order, $this->callbackPayload($attempt));
        $second = $gateway->verifyPayment($order, $this->callbackPayload($attempt));
        $this->assertTrue($first['verified']);
        $this->assertTrue($second['verified']);
        $this->assertSame('completed', $order->refresh()->status);
        $this->assertTrue($order->isPaid());
        $this->assertSame('gateway_verified', $attempt->refresh()->source);
        $this->assertSame('123456', $attempt->provider_reference);
        $this->assertNotNull($attempt->verified_at);
        $this->assertNull($attempt->confirmed_by);
        $this->assertNull($attempt->active_order_id);
        $this->assertSame($order->id, $attempt->settlement_order_id);
        Http::assertSentCount(1);
        $gateway->createPayment($order, 'retry-'.$attempt->id);
        Http::assertSentCount(1);
    }

    public static function verificationCodes(): array
    {
        return [[100], [101]];
    }

    #[DataProvider('invalidVerifications')]
    public function test_rejected_or_conflicting_verification_keeps_the_active_slot(array $data, array $errors, int $http): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        $this->fakeHttp(['*' => Http::response(['data' => $data, 'errors' => $errors], $http)]);
        $this->assertFalse(app(ZarinpalPaymentGateway::class)->verifyPayment($order, $this->callbackPayload($attempt))['verified']);
        $this->assertFalse($order->refresh()->isPaid());
        $this->assertSame('uncertain', $attempt->refresh()->status);
        $this->assertSame($order->id, $attempt->active_order_id);
        $this->assertNull($attempt->provider_reference);
    }

    public static function invalidVerifications(): array
    {
        return [
            'missing reference' => [['code' => 100], [], 200],
            'zero reference' => [['code' => 100, 'ref_id' => 0], [], 200],
            'forged flag' => [['verified' => true, 'ref_id' => 123], [], 200],
            'wrong amount echo' => [['code' => 100, 'ref_id' => 123, 'amount' => 100000], [], 200],
            'wrong authority echo' => [['code' => 100, 'ref_id' => 123, 'authority' => 'S'.str_repeat('b', 35)], [], 200],
            'wrong merchant echo' => [['code' => 100, 'ref_id' => 123, 'merchant_id' => 'wrong'], [], 200],
            'wrong currency echo' => [['code' => 100, 'ref_id' => 123, 'currency' => 'IRR'], [], 200],
            'amount rejected' => [[], ['code' => -50], 401],
            'not paid' => [[], ['code' => -51], 401], 'wrong merchant' => [[], ['code' => -53], 401],
            'HTTP 200 business errors' => [['code' => 100, 'ref_id' => 123], ['code' => -54], 200],
        ];
    }

    public function test_callback_requires_stored_authority_and_private_correlation_and_does_not_leak_orders(): void
    {
        $order = $this->order(['customer_name' => 'Private Buyer']);
        $attempt = $this->initiate($order);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []])]);
        $invalid = [[], ['order' => $order->order_number], [...$this->callbackPayload($attempt), 'ctx' => str_repeat('a', 64)],
            [...$this->callbackPayload($attempt), 'Authority' => $this->authority(2)], ['Authority' => ['bad'], 'Status' => 'OK']];
        foreach ($invalid as $payload) {
            $response = $this->get(route('payments.zarinpal.callback', $payload))->assertOk()
                ->assertDontSee('Private Buyer')->assertDontSee($order->order_number);
        }
        Http::assertNothingSent();
        $this->get(route('payments.zarinpal.callback', $this->callbackPayload($attempt)))->assertOk()->assertDontSee($order->order_number);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_cross_order_authority_cannot_settle_the_requested_order(): void
    {
        $first = $this->order();
        $a = $this->initiate($first);
        $second = $this->order();
        $b = $this->initiate($second, 2);
        $this->fakeHttp();
        $payload = [...$this->callbackPayload($a), 'Authority' => $b->authority];
        $this->assertFalse(app(ZarinpalPaymentGateway::class)->verifyPayment($first, $payload)['verified']);
        Http::assertNothingSent();
        $this->assertFalse($first->refresh()->isPaid());
        $this->assertFalse($second->refresh()->isPaid());
    }

    public function test_changed_merchant_environment_or_disabled_gateway_cannot_verify_an_old_attempt(): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        config(['payments.zarinpal.sandbox_merchant_id' => (string) Str::uuid()]);
        $this->fakeHttp();
        $this->assertFalse(app(ZarinpalPaymentGateway::class)->verifyPayment($order, $this->callbackPayload($attempt))['verified']);
        Http::assertNothingSent();
        $this->assertSame('pending', $attempt->refresh()->status);
    }

    #[DataProvider('inquiryStates')]
    public function test_inquiry_never_settles_by_itself_and_only_terminal_states_allow_retry(string $state, string $expected): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        $this->fakeSequence()->push(['data' => ['code' => 100, 'status' => $state], 'errors' => []])
            ->push(['data' => [], 'errors' => ['code' => -50]], 401);
        app(ZarinpalPaymentGateway::class)->reconcilePayment($order);
        $this->assertSame($expected, $attempt->refresh()->status);
        $this->assertFalse($order->refresh()->isPaid());
        $terminal = in_array($expected, ['failed', 'cancelled'], true);
        $this->assertSame($terminal ? null : $order->id, $attempt->active_order_id);
        Http::assertSentCount(in_array($state, ['PAID', 'VERIFIED'], true) ? 2 : 1);
    }

    public static function inquiryStates(): array
    {
        return [['IN_BANK', 'pending'], ['FAILED', 'failed'], ['REVERSED', 'cancelled'],
            ['PAID', 'uncertain'], ['VERIFIED', 'uncertain'], ['UNRECOGNIZED', 'uncertain']];
    }

    public function test_browser_nok_does_not_cancel_an_in_bank_payment_and_can_recover_actual_payment(): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        $this->fakeSequence()->push(['data' => ['code' => 100, 'status' => 'IN_BANK'], 'errors' => []])
            ->push(['data' => ['code' => 100, 'status' => 'PAID'], 'errors' => []])
            ->push(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []]);
        $gateway = app(ZarinpalPaymentGateway::class);
        $gateway->verifyPayment($order, $this->callbackPayload($attempt, 'NOK'));
        $this->assertSame('pending', $attempt->refresh()->status);
        $this->assertFalse($order->refresh()->isPaid());
        $gateway->verifyPayment($order, $this->callbackPayload($attempt, 'NOK'));
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('gateway_verified', $attempt->refresh()->source);
    }

    public function test_recovery_after_verified_but_local_commit_failed_uses_101_without_new_charge(): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        $failed = false;
        DB::listen(function ($query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'update "orders" set "payment_status"')) {
                $failed = true;
                throw new QueryException('sqlite', $query->sql, [], new \RuntimeException('Injected commit write failure'));
            }
        });
        $this->fakeSequence()->push(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []])
            ->push(['data' => ['code' => 100, 'status' => 'VERIFIED'], 'errors' => []])
            ->push(['data' => ['code' => 101, 'ref_id' => 123], 'errors' => []]);
        $gateway = app(ZarinpalPaymentGateway::class);
        $gateway->verifyPayment($order, $this->callbackPayload($attempt));
        $this->assertTrue($failed);
        $this->assertFalse($order->refresh()->isPaid());
        $this->assertSame('uncertain', $attempt->refresh()->status);
        $gateway->reconcilePayment($order);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('123', $attempt->refresh()->provider_reference);
        $this->assertDatabaseCount('payment_attempts', 1);
        Http::assertSentCount(3);
    }

    public function test_a_provider_reference_cannot_settle_two_orders(): void
    {
        $first = $this->order();
        $a = $this->initiate($first);
        $second = $this->order();
        $b = $this->initiate($second, 2);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []])]);
        $gateway = app(ZarinpalPaymentGateway::class);
        $gateway->verifyPayment($first, $this->callbackPayload($a));
        $gateway->verifyPayment($second, $this->callbackPayload($b));
        $this->assertTrue($first->refresh()->isPaid());
        $this->assertFalse($second->refresh()->isPaid());
        $this->assertSame('uncertain', $b->refresh()->status);
        $this->assertNull($b->settlement_order_id);
    }

    public function test_guest_checkout_redirect_and_receipt_keep_order_idempotency_and_exact_amount(): void
    {
        app(SettingsService::class)->set('payment_gateway', 'zarinpal');
        $product = Product::factory()->published()->create(['price' => '10000.00', 'sale_price' => null]);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'authority' => $this->authority()], 'errors' => []])]);
        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $form = $this->get(route('checkout.create'))->assertOk();
        $payload = ['customer_name' => 'Gateway Guest', 'customer_phone' => '09120000000',
            '_cart_review' => $form->viewData('cartReview'), '_checkout_intent' => $form->viewData('checkoutIntent')];
        $this->post(route('checkout.store'), $payload)->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$this->authority());
        $this->post(route('checkout.store'), $payload)->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$this->authority());
        $order = Order::query()->sole();
        $attempt = PaymentAttempt::query()->sole();
        $this->assertNull($order->user_id);
        $this->assertSame('10000.00', $order->total);
        Http::assertSentCount(1);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []])]);
        $callback = $this->get(route('payments.zarinpal.callback', $this->callbackPayload($attempt)))->assertRedirect();
        $this->get($callback->headers->get('Location'))->assertOk()->assertSee('پرداخت تأیید شد')->assertSee('مرجع درگاه')->assertSee('123');
        $this->assertDatabaseCount('order_items', 1);
    }

    public function test_retry_requires_signed_capability_and_account_owner_and_reuses_the_order(): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        $confirmation = app(OrderConfirmationUrl::class);
        $this->post(route('payments.zarinpal.retry', $order))->assertForbidden();
        $this->post($confirmation->paymentAction($order, 'retry'))->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$attempt->authority);
        $owner = User::factory()->client()->create();
        $owned = $this->order(['user_id' => $owner->id]);
        $url = $confirmation->paymentAction($owned, 'retry');
        $this->actingAs(User::factory()->client()->create(), 'client')->post($url)->assertNotFound();
        $this->actingAs($owner, 'client');
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'authority' => $this->authority(2)], 'errors' => []])]);
        $this->post($url)->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/'.$this->authority(2));
        $this->assertDatabaseCount('orders', 2);
        $this->assertSame($owner->id, $owned->refresh()->user_id);
    }

    public function test_production_is_disabled_by_default_and_credentials_are_not_a_connection_test(): void
    {
        config(['payments.zarinpal.environment' => 'production', 'payments.zarinpal.merchant_id' => (string) Str::uuid()]);
        $gateway = app(ZarinpalPaymentGateway::class);
        $this->assertFalse($gateway->isConfigured());
        $this->assertFalse($gateway->createPayment($this->order())['ok']);
        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_attempts', 0);
        config(['payments.zarinpal.production_enabled' => true]);
        $this->assertTrue($gateway->isConfigured());
        Http::assertNothingSent();
    }

    #[DataProvider('unsupportedAmounts')]
    public function test_unsupported_gateway_money_is_rejected_without_rounding_or_network(string $amount): void
    {
        $order = $this->order(['total' => $amount, 'subtotal' => $amount]);
        $this->assertFalse(app(ZarinpalPaymentGateway::class)->createPayment($order)['ok']);
        $this->assertSame($amount, $order->refresh()->total);
        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public static function unsupportedAmounts(): array
    {
        return [['0.00'], ['999.00'], ['1000.50'], ['100000001.00']];
    }

    public function test_received_bank_payment_preserves_a_cancelled_order_without_fulfilling_it(): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        // Simulate inconsistent historical cancellation; normal cancel refuses an active attempt.
        DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled']);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []])]);
        app(ZarinpalPaymentGateway::class)->verifyPayment($order, $this->callbackPayload($attempt));
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('gateway_verified', $attempt->refresh()->source);
    }

    public function test_parallel_verification_and_administrator_actions_cannot_bypass_active_attempt(): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        $actor = User::factory()->admin()->create();
        $calls = 0;
        $this->fakeHttp(function () use ($order, $attempt, $actor, &$calls) {
            $calls++;
            $this->assertFalse(app(ZarinpalPaymentGateway::class)->verifyPayment($order, $this->callbackPayload($attempt))['verified']);
            foreach ([fn () => app(OrderPaymentService::class)->confirmManual($order, $actor, 'Manual claim'),
                fn () => app(OrderPaymentService::class)->cancel($order, $actor),
                fn () => app(OrderPaymentService::class)->recordUnsettled($attempt, 'failed', 'initiation_unavailable')] as $operation) {
                try {
                    $operation();
                    $this->fail('An initiated payment was bypassed');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('payment', $exception->errors());
                }
            }

            return Http::response(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []]);
        });
        app(ZarinpalPaymentGateway::class)->verifyPayment($order, $this->callbackPayload($attempt));
        $this->assertSame(1, $calls);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_authority_collision_preserves_uncertainty_without_cross_order_redirect(): void
    {
        $first = $this->order();
        $this->initiate($first);
        $second = $this->order();
        $result = app(ZarinpalPaymentGateway::class)->createPayment($second);
        $this->assertNull($result['redirect_url']);
        $attempt = $second->paymentAttempts()->sole();
        $this->assertSame('uncertain', $attempt->status);
        $this->assertNull($attempt->authority);
        $this->assertSame($second->id, $attempt->active_order_id);
        $this->assertDatabaseCount('payment_attempts', 2);
    }

    public function test_payment_email_is_claimed_after_settlement_once_and_failure_does_not_rollback_payment(): void
    {
        $order = $this->order(['customer_email' => 'buyer@example.com']);
        $attempt = $this->initiate($order);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []])]);
        $sent = 0;
        Mail::shouldReceive('raw')->once()->andReturnUsing(function (string $body) use ($order, &$sent): void {
            $sent++;
            $this->assertTrue($order->fresh()->isPaid());
            $this->assertStringContainsString('Sandbox', $body);
            throw new \RuntimeException('Simulated mail delivery failure');
        });
        $gateway = app(ZarinpalPaymentGateway::class);
        $gateway->verifyPayment($order, $this->callbackPayload($attempt));
        $gateway->verifyPayment($order, $this->callbackPayload($attempt));
        $this->assertSame(1, $sent);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertNotNull($attempt->refresh()->notification_claimed_at);
    }

    public function test_fractional_checkout_gateway_amount_preserves_cart_and_does_not_create_order(): void
    {
        app(SettingsService::class)->set('payment_gateway', 'zarinpal');
        $product = Product::factory()->published()->create(['price' => '1000.50']);
        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $this->post(route('checkout.store'), ['customer_name' => 'Fractional buyer', 'customer_phone' => '09120000000'])
            ->assertSessionHasErrors('payment');
        $this->assertDatabaseCount('orders', 0);
        $this->assertCount(1, session('shop_cart'));
        Http::assertNothingSent();
    }

    public function test_settings_form_uses_rest_credentials_and_explicit_environments(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ManageSiteSettings::class)
            ->assertFormFieldExists('zarinpal_environment')->assertFormFieldExists('zarinpal_enabled')
            ->assertFormFieldExists('zarinpal_sandbox_merchant_id')->assertFormFieldExists('zarinpal_merchant_id')
            ->assertFormFieldDoesNotExist('zarinpal_access_token')->assertFormFieldDoesNotExist('zarinpal_graphql_endpoint');
    }

    public function test_upstream_exception_credentials_are_not_exposed_or_logged(): void
    {
        $merchant = config('payments.zarinpal.sandbox_merchant_id');
        Log::spy();
        $this->fakeHttp(function () use ($merchant) {
            throw new \RuntimeException('Secret credential '.$merchant);
        });
        $order = $this->order();
        $result = app(ZarinpalPaymentGateway::class)->createPayment($order);
        $this->assertStringNotContainsString($merchant, json_encode($result));
        $this->assertStringNotContainsString($merchant, json_encode($order->paymentAttempts()->sole()->getAttributes()));
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
    }

    #[DataProvider('invalidCallbackUrls')]
    public function test_callback_url_must_be_the_canonical_https_endpoint(string $url): void
    {
        config(['payments.zarinpal.callback_url' => $url]);
        $this->assertFalse(app(ZarinpalPaymentGateway::class)->isConfigured());
        Http::assertNothingSent();
    }

    public static function invalidCallbackUrls(): array
    {
        return [['http://king.test/payments/zarinpal/callback'], ['https://evil.test/payments/zarinpal/callback'],
            ['https://king.test/other'], ['https://user@king.test/payments/zarinpal/callback'],
            ['https://king.test/payments/zarinpal/callback?anything=1']];
    }

    public function test_legacy_paid_order_is_not_relabeled_as_gateway_verified_or_charged(): void
    {
        $order = $this->order();
        DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'paid', 'status' => 'paid']);
        $result = app(ZarinpalPaymentGateway::class)->createPayment($order);
        $this->assertFalse($result['verified']);
        $this->assertNull($result['redirect_url']);
        $this->assertDatabaseCount('payment_attempts', 0);
        Http::assertNothingSent();
    }

    public function test_old_nok_callback_does_not_reconcile_a_different_attempt(): void
    {
        $order = $this->order();
        $first = $this->initiate($order);
        $gateway = app(ZarinpalPaymentGateway::class);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'status' => 'FAILED'], 'errors' => []])]);
        $gateway->reconcilePayment($order);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'authority' => $this->authority(2)], 'errors' => []])]);
        $next = $gateway->createPayment($order, 'retry-'.$first->id);
        $second = PaymentAttempt::query()->findOrFail($next['attempt_id']);
        $this->fakeHttp();
        $gateway->verifyPayment($order, $this->callbackPayload($first, 'NOK'));
        Http::assertNothingSent();
        $this->assertSame('pending', $second->refresh()->status);
        $this->assertSame('failed', $first->refresh()->status);
        $this->assertFalse($order->refresh()->isPaid());
    }

    public function test_verify_timeout_preserves_the_attempt_and_inquiry_recovers_the_same_payment(): void
    {
        $order = $this->order();
        $attempt = $this->initiate($order);
        $calls = 0;
        $this->fakeHttp(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('Simulated timeout');
        });
        $gateway = app(ZarinpalPaymentGateway::class);
        $gateway->verifyPayment($order, $this->callbackPayload($attempt));
        $this->assertSame('uncertain', $attempt->refresh()->status);
        $gateway->createPayment($order, 'retry-'.$attempt->id);
        $this->assertSame(1, $calls);
        $this->assertSame($order->id, $attempt->active_order_id);
        $this->fakeSequence()->push(['data' => ['code' => 100, 'status' => 'VERIFIED'], 'errors' => []])
            ->push(['data' => ['code' => 101, 'ref_id' => 123], 'errors' => []]);
        $gateway->reconcilePayment($order);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertDatabaseCount('payment_attempts', 1);
    }

    public function test_sandbox_reference_does_not_collide_with_a_distinct_production_transaction(): void
    {
        $sandbox = $this->order();
        $first = $this->initiate($sandbox);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []])]);
        $gateway = app(ZarinpalPaymentGateway::class);
        $gateway->verifyPayment($sandbox, $this->callbackPayload($first));
        config(['payments.zarinpal.environment' => 'production', 'payments.zarinpal.merchant_id' => (string) Str::uuid(),
            'payments.zarinpal.production_enabled' => true]);
        $production = $this->order();
        $authority = 'A'.str_repeat('0', 35);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'authority' => $authority], 'errors' => []])]);
        $result = $gateway->createPayment($production);
        $this->assertSame('https://payment.zarinpal.com/pg/StartPay/'.$authority, $result['redirect_url']);
        $second = PaymentAttempt::query()->findOrFail($result['attempt_id']);
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'ref_id' => 123], 'errors' => []])]);
        $gateway->verifyPayment($production, $this->callbackPayload($second));
        $this->assertTrue($sandbox->refresh()->isPaid());
        $this->assertTrue($production->refresh()->isPaid());
        $this->assertSame('sandbox', $first->refresh()->environment);
        $this->assertSame('production', $second->refresh()->environment);
        $this->assertSame('تأیید آزمایشی درگاه (Sandbox)', $sandbox->paymentProvenanceLabel());
    }

    private function fakeHttp(mixed $responses = null): mixed
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();

        return Http::fake($responses);
    }

    private function fakeSequence(): ResponseSequence
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();

        return Http::fakeSequence();
    }

    protected function initiate(Order $order, int $authority = 1): PaymentAttempt
    {
        $this->fakeHttp(['*' => Http::response(['data' => ['code' => 100, 'authority' => $this->authority($authority)], 'errors' => []])]);
        $result = app(ZarinpalPaymentGateway::class)->createPayment($order);
        $this->assertTrue($result['ok']);

        return PaymentAttempt::query()->findOrFail($result['attempt_id']);
    }

    protected function callbackPayload(PaymentAttempt $attempt, string $status = 'OK'): array
    {
        return ['Authority' => $attempt->authority, 'Status' => $status, 'ctx' => app(ZarinpalConfiguration::class)->callbackContext($attempt)];
    }

    protected function authority(int $id = 1): string
    {
        return 'S'.str_pad((string) $id, 35, '0', STR_PAD_LEFT);
    }

    protected function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'order_number' => 'P3-'.Str::uuid(), 'customer_name' => 'P3 Buyer', 'customer_phone' => '09120000000',
            'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'zarinpal',
            'subtotal' => '10000.00', 'total' => '10000.00', ...$attributes,
        ]);
    }
}
