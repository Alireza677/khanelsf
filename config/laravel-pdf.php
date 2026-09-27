<?php

return [
    'driver' => env('LARAVEL_PDF_DRIVER', 'chrome'),

    'chrome' => [
        'chrome_binary' => env('LARAVEL_PDF_CHROME_BINARY'),
        'no_sandbox' => env('LARAVEL_PDF_CHROME_NO_SANDBOX', false),
        'startup_timeout' => env('LARAVEL_PDF_CHROME_STARTUP_TIMEOUT', 30),
        'timeout' => env('LARAVEL_PDF_CHROME_TIMEOUT', 30000),
        'operation_timeout' => env('LARAVEL_PDF_CHROME_OPERATION_TIMEOUT', 5000),
        'user_data_dir' => env('LARAVEL_PDF_CHROME_USER_DATA_DIR'),
        'custom_flags' => [],
        'env_variables' => [],
    ],

    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://127.0.0.1:3000'),
        'username' => env('GOTENBERG_USERNAME'),
        'password' => env('GOTENBERG_PASSWORD'),
    ],
];
