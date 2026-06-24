<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Media disk
    |--------------------------------------------------------------------------
    |
    | Store uploaded CMS media on Laravel's public disk so files are available
    | through the /storage symlink created by `php artisan storage:link`.
    |
    */

    'disk_name' => env('MEDIA_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Upload limit
    |--------------------------------------------------------------------------
    */

    'max_file_size' => 1024 * 1024 * 10,

    /*
    |--------------------------------------------------------------------------
    | Queue conversions
    |--------------------------------------------------------------------------
    |
    | The models define one small admin thumbnail and mark it nonQueued(), so
    | this can stay false for a simple starter project.
    |
    */

    'queue_conversions_by_default' => false,
];
