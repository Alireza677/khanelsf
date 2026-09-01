<?php

namespace App\Services;

use App\Exceptions\BackupOperationException;
use App\Models\Page;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class BackupRestoreHealthCheck
{
    public function assertHealthy(): void
    {
        try {
            DB::select('select 1');
            foreach (['migrations', 'users', 'pages', 'settings', 'backups', 'backup_restores'] as $table) {
                if (! Schema::hasTable($table)) {
                    throw new \RuntimeException("Missing table {$table}");
                }
            }
            Page::query()->limit(1)->get();
            Setting::query()->limit(1)->get();
            foreach (config('backup.persistent_disks', []) as $definition) {
                $root = (string) ($definition['root'] ?? '');
                if ($root === '') {
                    throw new \RuntimeException('Missing storage root.');
                }
                File::ensureDirectoryExists($root, 0700, true);
                $probe = $root.DIRECTORY_SEPARATOR.'.restore-health-'.bin2hex(random_bytes(6));
                if (@file_put_contents($probe, 'ok', LOCK_EX) !== 2 || @file_get_contents($probe) !== 'ok') {
                    throw new \RuntimeException('Storage is not writable.');
                }
                @unlink($probe);
            }
        } catch (\Throwable $exception) {
            throw new BackupOperationException('restore_health_check_failed', 'بررسی سلامت سایت پس از بازیابی ناموفق بود.', $exception);
        }
    }
}
