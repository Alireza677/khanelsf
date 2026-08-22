<?php

namespace App\Enums;

enum BackupType: string
{
    case Full = 'full';
    case Database = 'database';
    case Files = 'files';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'کامل',
            self::Database => 'پایگاه داده',
            self::Files => 'فایل‌ها',
        };
    }
}
