<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Models\Order;

class ZarinpalPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ZarinpalGraphqlClient $client,
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
        return 'Online payment with Zarinpal is selected, but the real payment mutations must be completed before production use.';
    }

    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    public function createPayment(Order $order): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'redirect_url' => null,
                'error' => 'Zarinpal access token is not configured.',
            ];
        }

        return [
            'ok' => false,
            'redirect_url' => null,
            'error' => 'Zarinpal payment creation is not implemented yet. Complete the official GraphQL mutation details before enabling live payments.',
        ];
    }

    public function verifyPayment(Order $order, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'verified' => false,
                'error' => 'Zarinpal access token is not configured.',
            ];
        }

        return [
            'ok' => false,
            'verified' => false,
            'error' => 'Zarinpal payment verification is not implemented yet. Complete the official GraphQL verification mutation details before enabling live payments.',
        ];
    }

    public function callbackUrl(): string
    {
        return trim((string) ($this->settings->get('zarinpal_callback_url')
            ?: route('payments.zarinpal.callback')));
    }
}
