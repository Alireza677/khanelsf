<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use Illuminate\Support\Facades\Artisan;

class BackupMaintenanceMode
{
    public function enter(): void
    {
        if (Artisan::call('down', ['--retry' => 60]) !== 0) {
            throw new BackupOperationException('restore_maintenance_failed', 'ورود سایت به حالت نگهداری ناموفق بود.');
        }
    }

    public function leave(): void
    {
        if (Artisan::call('up') !== 0) {
            throw new BackupOperationException('restore_maintenance_exit_failed', 'خروج سایت از حالت نگهداری ناموفق بود.');
        }
    }
}
