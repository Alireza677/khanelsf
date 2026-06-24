<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ZarinpalGraphqlClient
{
    public const DEFAULT_ENDPOINT = 'https://next.zarinpal.com/api/v4/graphql/';

    public function __construct(private readonly SettingsService $settings) {}

    public function post(string $query, array $variables = []): array
    {
        $token = $this->accessToken();

        if ($token === '') {
            return [
                'ok' => false,
                'data' => null,
                'error' => 'Zarinpal access token is not configured.',
            ];
        }

        try {
            $response = Http::acceptJson()
                ->withToken($token)
                ->post($this->endpoint(), [
                    'query' => $query,
                    'variables' => $variables,
                ]);

            if ($response->failed()) {
                Log::warning('Zarinpal GraphQL request failed.', [
                    'status' => $response->status(),
                ]);

                return [
                    'ok' => false,
                    'data' => null,
                    'error' => 'Zarinpal request failed.',
                ];
            }

            return [
                'ok' => true,
                'data' => $response->json(),
                'error' => null,
            ];
        } catch (Throwable $exception) {
            Log::warning('Zarinpal GraphQL request exception.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'data' => null,
                'error' => 'Zarinpal request could not be completed.',
            ];
        }
    }

    public function isConfigured(): bool
    {
        return $this->accessToken() !== '';
    }

    public function endpoint(): string
    {
        $endpoint = trim((string) $this->settings->get('zarinpal_graphql_endpoint', self::DEFAULT_ENDPOINT));

        return $endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT;
    }

    private function accessToken(): string
    {
        return trim((string) $this->settings->get('zarinpal_access_token', ''));
    }
}
