<?php

use App\Services\FormNotifications\EmailFormSubmissionNotificationChannel;

return [
    'page_editor_history_limit' => (int) env('CMS_PAGE_EDITOR_HISTORY_LIMIT', 60),
    'page_editor_history_max_bytes' => (int) env('CMS_PAGE_EDITOR_HISTORY_MAX_BYTES', 6 * 1024 * 1024),
    'page_editor_history_store' => env('CMS_PAGE_EDITOR_HISTORY_STORE'),
    'page_editor_history_ttl_seconds' => (int) env('CMS_PAGE_EDITOR_HISTORY_TTL_SECONDS', 4 * 60 * 60),
    'hero_v2_editor' => (bool) env('CMS_HERO_V2_EDITOR', false),
    'default_font' => [
        'family' => 'CMS Default Persian',
        'filename' => 'cms-default-persian.woff2',
    ],
    'form_notifications' => [
        'channels' => [
            EmailFormSubmissionNotificationChannel::class,
        ],
    ],
];
