<?php

use App\Services\FormNotifications\EmailFormSubmissionNotificationChannel;

return [
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
