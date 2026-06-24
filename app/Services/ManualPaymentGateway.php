<?php

namespace App\Services;

use App\Contracts\PaymentGateway;

class ManualPaymentGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsService $settings) {}

    public function method(): string
    {
        return 'manual';
    }

    public function initialPaymentStatus(): string
    {
        return 'unpaid';
    }

    public function instructions(): string
    {
        return (string) ($this->settings->get('shop_manual_payment_message')
            ?: 'پرداخت فعلا به‌صورت دستی انجام می‌شود. برای تایید پرداخت و تکمیل سفارش با شما تماس می‌گیریم.');
    }
}
