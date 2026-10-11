<?php

namespace App\Enums;

enum ClientProjectDeliveryMode: string
{
    case Time = 'time';
    case Tasks = 'tasks';
    case Hybrid = 'hybrid';

    public static function options(): array
    {
        return ['time' => 'زمان‌محور', 'tasks' => 'وظیفه‌محور', 'hybrid' => 'ترکیبی'];
    }
}
