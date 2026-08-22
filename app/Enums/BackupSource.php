<?php

namespace App\Enums;

enum BackupSource: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
    case Uploaded = 'uploaded';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'ایجادشده در CMS',
            self::Uploaded => 'آپلودشده',
            self::Automatic => 'خودکار (قدیمی)',
        };
    }
}
