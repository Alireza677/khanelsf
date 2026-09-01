<?php

namespace App\Enums;

enum BackupRestoreStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case CreatingSafetyBackup = 'creating_safety_backup';
    case EnteringMaintenance = 'entering_maintenance';
    case RestoringDatabase = 'restoring_database';
    case Migrating = 'migrating';
    case RestoringStorage = 'restoring_storage';
    case HealthCheck = 'health_check';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'در صف بازیابی',
            self::Validating => 'در حال اعتبارسنجی',
            self::CreatingSafetyBackup => 'در حال ایجاد نسخه ایمنی',
            self::EnteringMaintenance => 'در حال ورود به حالت نگهداری',
            self::RestoringDatabase => 'در حال بازیابی پایگاه داده',
            self::Migrating => 'در حال تطبیق ساختار پایگاه داده',
            self::RestoringStorage => 'در حال بازیابی فایل‌ها',
            self::HealthCheck => 'در حال بررسی سلامت',
            self::Completed => 'بازیابی تکمیل شد',
            self::Failed => 'بازیابی ناموفق بود',
        };
    }

    public function terminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
