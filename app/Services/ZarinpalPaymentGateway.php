<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\PaymentAttempt;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class ZarinpalPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly ZarinpalConfiguration $configuration,
        private readonly ZarinpalApiClient $client,
        private readonly OrderPaymentService $payments,
    ) {}

    public function method(): string
    {
        return 'zarinpal';
    }

    public function initialPaymentStatus(): string
    {
        return Order::PAYMENT_STATUS_UNPAID;
    }

    public function instructions(): string
    {
        return 'ثبت سفارش به معنی پرداخت نیست. نتیجه پرداخت پس از بررسی درگاه در رسید سفارش نمایش داده می‌شود.';
    }

    public function isConfigured(): bool
    {
        return $this->configuration->configured();
    }

    public function validateAmount(string $amount): void
    {
        $this->configuration->gatewayAmount($amount);
    }

    public function createPayment(Order $order, string $intent = 'initial'): array
    {
        if ($order->refresh()->isPaid()) {
            $verified = $order->paymentAttempts()->where('provider', 'zarinpal')->where('source', 'gateway_verified')
                ->where('status', PaymentAttempt::SUCCEEDED)->where('settlement_order_id', $order->id)->whereNotNull('verified_at')->exists();

            return ['ok' => false, 'verified' => $verified, 'redirect_url' => null, 'error' => null];
        }
        try {
            $this->configuration->assertConfigured();
            $attempt = $this->payments->prepareGateway($order, $intent, $this->configuration);
            if (! $this->configuration->matches($attempt)) {
                return $this->unavailable();
            }
            // An expired initiation lease is never permission to send the request again.
            if ($attempt->requested_at && ! $attempt->authority && $attempt->operation === 'initiate'
                && $attempt->operation_expires_at?->isPast()) {
                $attempt = $this->payments->finishGatewayInitiation($attempt, $attempt->operation_token, null, null);
            }
            $token = $this->payments->initiateGateway($attempt);
            if ($token !== null) {
                $response = $this->client->call('request', [
                    'amount' => (int) $attempt->gateway_amount, 'currency' => 'IRT',
                    'callback_url' => $this->configuration->callbackUrl($attempt),
                    'description' => 'پرداخت سفارش '.$order->order_number,
                    'metadata' => ['order_id' => (string) $order->id],
                ], $attempt);
                $authority = $response['kind'] === 'business' && $response['code'] === 100
                    && $this->configuration->validAuthority($response['data']['authority'] ?? null)
                    && $this->echoMatches($attempt, $response['data'])
                    ? $response['data']['authority'] : null;
                try {
                    $attempt = $this->payments->finishGatewayInitiation($attempt, $token, $authority,
                        $response['kind'] === 'business' && $response['code'] < 0 ? $response['code'] : null);
                } catch (QueryException|ValidationException) {
                    // The provider may have accepted it; never retry the charge after a local conflict.
                    $attempt = $this->payments->finishGatewayInitiation($attempt, $token, null, null);
                }
            }

            return $this->result($attempt->refresh());
        } catch (ValidationException) {
            return $this->unavailable();
        }
    }

    /** Only stored authority + a server-created correlation capability can enter this path. */
    public function callbackAttempt(string $authority, string $context): ?PaymentAttempt
    {
        if (! $this->configuration->validAuthority($authority) || ! preg_match('/^[a-f0-9]{64}$/D', $context)) {
            return null;
        }
        $attempt = PaymentAttempt::query()->where('provider', 'zarinpal')->where('authority', $authority)->first();
        if (! $attempt || ! hash_equals($this->configuration->callbackContext($attempt), $context)
            || ! $this->configuration->matches($attempt)) {
            return null;
        }

        return $attempt;
    }

    public function verifyPayment(Order $order, array $payload = []): array
    {
        $authority = $payload['Authority'] ?? null;
        $context = $payload['ctx'] ?? null;
        if (! is_string($authority) || ! is_string($context)
            || ! in_array($payload['Status'] ?? null, ['OK', 'NOK'], true)) {
            return $this->unavailable();
        }
        $attempt = $this->callbackAttempt($authority, $context);
        if (! $attempt || $attempt->order_id !== $order->id) {
            return $this->unavailable();
        }

        return $payload['Status'] === 'OK' ? $this->verifyAttempt($attempt) : $this->reconcilePayment($order, $attempt);
    }

    public function reconcilePayment(Order $order, ?PaymentAttempt $correlated = null): array
    {
        $attempt = $correlated ? $correlated->fresh() : $order->paymentAttempts()->where('provider', 'zarinpal')->latest('id')->first();
        if (! $attempt || $attempt->order_id !== $order->id || ! $this->configuration->matches($attempt)) {
            return $this->unavailable();
        }
        if ($attempt->status === PaymentAttempt::SUCCEEDED || $order->refresh()->isPaid()) {
            return $this->result($attempt);
        }
        $token = $this->payments->claimGatewayCheck($attempt, 'inquiry');
        if ($token === null) {
            return $this->result($attempt->refresh());
        }
        $response = $this->client->call('inquiry', ['authority' => $attempt->authority], $attempt);
        $state = $response['kind'] === 'business' && $response['code'] === 100 ? ($response['data']['status'] ?? null) : null;
        [$status, $diagnostic] = match ($state) {
            'FAILED' => [PaymentAttempt::FAILED, 'provider_failed'],
            'REVERSED' => [PaymentAttempt::CANCELLED, 'provider_reversed'],
            'IN_BANK' => [PaymentAttempt::PENDING, 'provider_in_bank'],
            'PAID', 'VERIFIED' => [PaymentAttempt::UNCERTAIN, 'provider_paid'],
            default => [PaymentAttempt::UNCERTAIN, 'inquiry_unknown'],
        };
        $attempt = $this->payments->finishGatewayCheck($attempt, $token, $status, $diagnostic, $response['code']);

        // Inquiry is not settlement evidence, even if it says VERIFIED.
        return in_array($state, ['PAID', 'VERIFIED'], true)
            ? $this->verifyAttempt($attempt) : $this->result($attempt);
    }

    private function verifyAttempt(PaymentAttempt $attempt): array
    {
        if (! $this->configuration->matches($attempt)) {
            return $this->unavailable();
        }
        $token = $this->payments->claimGatewayCheck($attempt, 'verify');
        if ($token === null) {
            return $this->result($attempt->refresh());
        }
        $response = $this->client->call('verify', [
            'authority' => $attempt->authority, 'amount' => (int) $attempt->gateway_amount,
        ], $attempt);
        $reference = $response['data']['ref_id'] ?? null;
        if ($response['kind'] === 'business' && in_array($response['code'], [100, 101], true)
            && (is_int($reference) || is_string($reference)) && preg_match('/^[1-9][0-9]{0,39}$/D', (string) $reference)
            && $this->echoMatches($attempt, $response['data'])) {
            $proof = new ZarinpalVerification($attempt->id, $attempt->order_id, $attempt->environment,
                $attempt->merchant_context, $attempt->authority, $attempt->gateway_amount, 'IRT', (string) $reference, $response['code']);
            try {
                $attempt = $this->payments->settleGateway($attempt, $proof, $token);
            } catch (QueryException|ValidationException) {
                $attempt = $this->payments->finishGatewayCheck($attempt, $token, PaymentAttempt::UNCERTAIN, 'persistence_conflict');
            }
        } else {
            $attempt = $this->payments->finishGatewayCheck($attempt, $token, PaymentAttempt::UNCERTAIN,
                $response['kind'] === 'business' && $response['code'] < 0 ? 'verification_rejected' : 'verification_unknown', $response['code']);
        }

        return $this->result($attempt);
    }

    private function echoMatches(PaymentAttempt $attempt, array $data): bool
    {
        // These echoes are not required by REST v4; if present they must not contradict the request.
        foreach (['authority' => $attempt->authority, 'currency' => 'IRT', 'amount' => $attempt->gateway_amount,
            'merchant_id' => $this->configuration->merchantId()] as $field => $expected) {
            if ($field === 'authority' && $expected === null) {
                continue;
            }
            $validType = ! array_key_exists($field, $data) || ($field === 'amount'
                ? is_int($data[$field]) || is_string($data[$field]) : is_string($data[$field]));
            if (array_key_exists($field, $data) && (! $validType || (string) $data[$field] !== (string) $expected)) {
                return false;
            }
        }

        return true;
    }

    private function result(PaymentAttempt $attempt): array
    {
        $redirect = $attempt->status === PaymentAttempt::PENDING && $this->configuration->validAuthority($attempt->authority)
            ? $this->configuration->host().'/pg/StartPay/'.$attempt->authority : null;

        return [
            'ok' => $redirect !== null, 'verified' => $attempt->status === PaymentAttempt::SUCCEEDED && $attempt->source === 'gateway_verified'
                && $attempt->verified_at !== null && $attempt->order->isPaid(),
            'redirect_url' => $redirect, 'attempt_id' => $attempt->id, 'state' => $attempt->status,
            'error' => $redirect === null ? 'نتیجه پرداخت را در رسید سفارش بررسی کنید.' : null,
        ];
    }

    private function unavailable(): array
    {
        return ['ok' => false, 'verified' => false, 'redirect_url' => null, 'error' => 'پرداخت در حال حاضر قابل انجام نیست؛ از رسید سفارش وضعیت را بررسی کنید.'];
    }

    public function callbackUrl(): string
    {
        return $this->configuration->callbackBase();
    }
}
