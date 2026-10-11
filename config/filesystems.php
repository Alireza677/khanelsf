<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'service_deliveries' => [
            'driver' => 'local',
            'root' => storage_path('app/service-deliveries-private'),
            'visibility' => 'private',
            'directory_visibility' => 'private',
            'serve' => false,
            'throw' => true,
        ],
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
