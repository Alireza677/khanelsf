<?php

return [
    'zarinpal' => [
        'enabled' => env('ZARINPAL_ENABLED', false),
        'environment' => env('ZARINPAL_ENVIRONMENT', 'sandbox'),
        'sandbox_merchant_id' => env('ZARINPAL_SANDBOX_MERCHANT_ID', ''),
        'merchant_id' => env('ZARINPAL_MERCHANT_ID', ''),
        // A separate deployment gate: selecting production in the panel is insufficient.
        'production_enabled' => env('ZARINPAL_PRODUCTION_ENABLED', false),
        'callback_url' => env('ZARINPAL_CALLBACK_URL'),
        'timeout' => (int) env('ZARINPAL_TIMEOUT', 15),
    ],
];
