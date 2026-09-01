<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use Illuminate\Support\Facades\Artisan;

class BackupRestoreMigrationService
{
    public function migrate(array $manifest): void
    {
        $laravelVersion = data_get($manifest, 'application.laravel_version');
        if (is_string($laravelVersion) && explode('.', $laravelVersion)[0] !== explode('.', app()->version())[0]) {
            throw new BackupOperationException('restore_migration_incompatible', 'نسخه Backup برای اجرای migrationهای فعلی سازگار نیست.');
        }
        if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
            throw new BackupOperationException('restore_migration_failed', 'تطبیق ساختار پایگاه داده پس از بازیابی ناموفق بود.');
        }
    }
}
