<?php

return [
    'queue' => env('BACKUP_QUEUE', 'backups'),
    'timeout' => (int) env('BACKUP_TIMEOUT', 3600),
    'chunk_size' => (int) env('BACKUP_CHUNK_SIZE_MB', 8) * 1024 * 1024,
    'database_dump_binary' => env('BACKUP_DB_DUMP_BINARY', 'mysqldump'),
    'temporary_disk' => 'local',
    'temporary_prefix' => 'backups/tmp',
    'files_prefix' => 'backups/files',
    'incoming_prefix' => 'backups/incoming',
    'local_retention_count' => 3,
    'upload_max_mb' => (int) env('BACKUP_UPLOAD_MAX_MB', 2048),
    'manifest_max_bytes' => 1024 * 1024,
    'orphan_ttl_hours' => (int) env('BACKUP_TEMP_TTL_HOURS', 24),
    'persistent_disks' => [
        'public' => [
            'root' => storage_path('app/public'),
            'excludes' => ['livewire-tmp', 'backups', '.env'],
        ],
    ],
];
