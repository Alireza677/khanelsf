<?php

namespace App\Services;

use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class ZarinpalApiClient
{
    public function __construct(private readonly ZarinpalConfiguration $configuration) {}

    /** REST v4 only; no automatic retries, response redirects, provider text or credentials in errors. */
    public function call(string $operation, array $payload, PaymentAttempt $attempt): array
    {
        if (! in_array($operation, ['request', 'verify', 'inquiry'], true)) {
            throw new RuntimeException('Unsupported Zarinpal operation.');
        }
        if (! $this->configuration->configured()) {
            return $this->unknown('configuration_changed');
        }
        $merchant = $this->configuration->merchantId();
        $environment = $this->configuration->environment();
        $context = hash_hmac('sha256', $environment.'|'.strtolower($merchant), config('app.key'));
        if ($attempt->environment !== $environment || $attempt->merchant_context !== $context || $attempt->provider !== 'zarinpal') {
            return $this->unknown('configuration_changed');
        }
        $host = $environment === 'sandbox' ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
        try {
            $response = Http::asJson()->acceptJson()->connectTimeout(5)->timeout($this->configuration->timeout())
                ->withOptions([
                    'allow_redirects' => false, 'verify' => true,
                    'progress' => static function ($total, $downloaded): void {
                        if ($total > 65536 || $downloaded > 65536) {
                            throw new RuntimeException('Provider response exceeds the allowed size.');
                        }
                    },
                ])->post($host.'/pg/v4/payment/'.$operation.'.json', [
                    ...$payload, 'merchant_id' => $merchant,
                ]);
            if (strlen($response->body()) > 65536) {
                return $this->unknown('oversized_response');
            }
            $json = json_decode($response->body(), true, 32, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            if (! is_array($json)) {
                return $this->unknown('malformed_response');
            }
            $data = $json['data'] ?? null;
            $errors = $json['errors'] ?? null;
            if ($response->status() === 200 && is_array($data) && is_int($data['code'] ?? null) && $data['code'] >= 0 && $errors === []) {
                return ['kind' => 'business', 'code' => $data['code'], 'data' => $data];
            }
            if (in_array($response->status(), [200, 400, 401, 403, 422, 429], true)
                && ($data === null || $data === []) && is_array($errors) && is_int($errors['code'] ?? null) && $errors['code'] < 0) {
                return ['kind' => 'business', 'code' => $errors['code'], 'data' => []];
            }

            return $this->unknown('invalid_business_response');
        } catch (Throwable) {
            // Exceptions may include a Merchant ID/request body. Never log/rethrow them.
            return $this->unknown('transport_unknown');
        }
    }

    private function unknown(string $code): array
    {
        return ['kind' => 'uncertain', 'diagnostic' => $code, 'data' => [], 'code' => null];
    }
}
