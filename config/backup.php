<?php

return [
    'queue' => env('BACKUP_QUEUE', 'backups'),
    'timeout' => (int) env('BACKUP_TIMEOUT', 3600),
    'chunk_size' => (int) env('BACKUP_CHUNK_SIZE_MB', 8) * 1024 * 1024,
    'database_dump_binary' => env('BACKUP_DB_DUMP_BINARY', 'mysqldump'),
    'database_restore_binary' => env('BACKUP_DB_RESTORE_BINARY', 'mysql'),
    'temporary_disk' => 'local',
    'temporary_prefix' => 'backups/tmp',
    'files_prefix' => 'backups/files',
    'incoming_prefix' => 'backups/incoming',
    'local_retention_count' => 3,
    'upload_max_mb' => 512,
    'manifest_max_bytes' => 1024 * 1024,
    'restore_max_entries' => 200000,
    'restore_max_uncompressed_bytes' => 20 * 1024 * 1024 * 1024,
    'restore_max_compression_ratio' => 200,
    'restore_state_prefix' => 'backups/restores',
    'restore_timeout' => (int) env('BACKUP_RESTORE_TIMEOUT', 7200),
    'restore_dispatch_delay_seconds' => 3,
    'progress_token_terminal_ttl_minutes' => 5,
    // Installation runtime state must survive historical application-data restores.
    'operational_tables' => [
        'jobs',
        'failed_jobs',
        'job_batches',
        'cache',
        'cache_locks',
        'sessions',
        'password_reset_tokens',
        'backups',
        'backup_restores',
    ],
    'orphan_ttl_hours' => (int) env('BACKUP_TEMP_TTL_HOURS', 24),
    'persistent_disks' => [
        'public' => [
            'root' => storage_path('app/public'),
            'excludes' => ['livewire-tmp', 'backups', '.env'],
        ],
    ],
];
