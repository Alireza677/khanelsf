<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ZarinpalConfiguration
{
    public function __construct(private readonly SettingsService $settings) {}

    public function environment(): string
    {
        return (string) $this->value('environment');
    }

    public function merchantId(): string
    {
        $key = $this->environment() === 'sandbox' ? 'sandbox_merchant_id' : 'merchant_id';

        return trim((string) $this->value($key));
    }

    public function enabled(): bool
    {
        return filter_var($this->value('enabled'), FILTER_VALIDATE_BOOLEAN)
            && in_array($this->environment(), ['sandbox', 'production'], true)
            && ($this->environment() !== 'production' || config('payments.zarinpal.production_enabled') === true);
    }

    public function configured(): bool
    {
        return $this->enabled() && Str::isUuid($this->merchantId()) && $this->validCallback();
    }

    public function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw ValidationException::withMessages(['payment' => 'درگاه فعال یا پیکربندی معتبر نیست؛ محیط، شناسه پذیرنده و نشانی HTTPS بازگشت را بررسی کنید.']);
        }
    }

    public function host(): string
    {
        return $this->environment() === 'sandbox' ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
    }

    public function context(): string
    {
        return hash_hmac('sha256', $this->environment().'|'.strtolower($this->merchantId()), config('app.key'));
    }

    public function matches(PaymentAttempt $attempt): bool
    {
        return $this->configured() && $attempt->provider === 'zarinpal'
            && $attempt->environment === $this->environment()
            && is_string($attempt->merchant_context) && hash_equals($this->context(), $attempt->merchant_context);
    }

    public function timeout(): int
    {
        return max(3, min(60, (int) config('payments.zarinpal.timeout', 15)));
    }

    public function callbackBase(): string
    {
        return trim((string) ($this->value('callback_url') ?: route('payments.zarinpal.callback')));
    }

    public function callbackContext(PaymentAttempt $attempt): string
    {
        return hash_hmac('sha256', 'zarinpal-callback|'.$attempt->id.'|'.$attempt->initiation_key, config('app.key'));
    }

    public function callbackUrl(PaymentAttempt $attempt): string
    {
        return $this->callbackBase().'?ctx='.$this->callbackContext($attempt);
    }

    public function validAuthority(mixed $authority): bool
    {
        $prefix = $this->environment() === 'sandbox' ? 'S' : 'A';

        return is_string($authority) && preg_match('/^'.$prefix.'[A-Za-z0-9]{35}$/D', $authority) === 1;
    }

    /** IRT is explicitly supported by REST v4; Sandbox verifies the identical integer. */
    public function gatewayAmount(string $amount): string
    {
        $amount = app(ProductPricingService::class)->amount($amount);
        if (bccomp($amount, '1000.00', 2) < 0 || bccomp($amount, '100000000.00', 2) > 0
            || ! str_ends_with($amount, '.00') || PHP_INT_SIZE < 8) {
            throw ValidationException::withMessages(['payment' => 'مبلغ درگاه باید تومان صحیح و بین ۱٬۰۰۰ تا ۱۰۰٬۰۰۰٬۰۰۰ تومان باشد؛ گردکردن مبلغ مجاز نیست.']);
        }

        return substr($amount, 0, -3);
    }

    private function validCallback(): bool
    {
        $url = parse_url($this->callbackBase());
        $canonical = parse_url(route('payments.zarinpal.callback'));

        return is_array($url) && ($url['scheme'] ?? '') === 'https'
            && strtolower($url['host'] ?? '') === strtolower($canonical['host'] ?? '')
            && ($url['port'] ?? 443) === ($canonical['port'] ?? 443)
            && ($url['path'] ?? '') === ($canonical['path'] ?? '')
            && ! isset($url['user']) && ! isset($url['pass']) && ! isset($url['query']) && ! isset($url['fragment']);
    }

    private function value(string $key): mixed
    {
        $fallback = config('payments.zarinpal.'.$key);
        $value = $this->settings->get('zarinpal_'.$key, $fallback);

        return in_array($key, ['sandbox_merchant_id', 'merchant_id', 'callback_url'], true) && blank($value) ? $fallback : $value;
    }
}
