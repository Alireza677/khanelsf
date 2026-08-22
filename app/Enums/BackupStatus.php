<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Queued = 'queued';
    case Creating = 'creating';
    case Uploading = 'uploading';
    case Verifying = 'verifying';
    case Completed = 'completed';
    case Failed = 'failed';
    case DeleteFailed = 'delete_failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'در صف',
            self::Creating => 'در حال ساخت',
            self::Uploading => 'در حال ارسال',
            self::Verifying => 'در حال بررسی',
            self::Completed => 'موفق',
            self::Failed => 'ناموفق',
            self::DeleteFailed => 'حذف ناموفق',
        };
    }
}
