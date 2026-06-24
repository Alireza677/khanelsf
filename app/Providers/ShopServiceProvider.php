<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Services\ManualPaymentGateway;
use App\Services\SettingsService;
use App\Services\ZarinpalPaymentGateway;
use Illuminate\Support\ServiceProvider;

class ShopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            $settings = $app->make(SettingsService::class);

            return match ((string) $settings->get('payment_gateway', 'manual')) {
                'zarinpal' => $app->make(ZarinpalPaymentGateway::class),
                default => $app->make(ManualPaymentGateway::class),
            };
        });
    }
}
