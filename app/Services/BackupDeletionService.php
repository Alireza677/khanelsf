<?php

namespace App\Services;

use App\Enums\BackupStatus;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use Illuminate\Support\Facades\Storage;

class BackupDeletionService
{
    public function delete(Backup $backup): void
    {
        try {
            if (filled($backup->local_disk) && filled($backup->local_path)) {
                $root = trim((string) config('backup.files_prefix', 'backups/files'), '/').'/';
                if (! str_starts_with(str_replace('\\', '/', $backup->local_path), $root)) {
                    throw new BackupOperationException('unsafe_backup_path', 'مسیر فایل نسخه پشتیبان معتبر نیست.');
                }
                Storage::disk($backup->local_disk)->delete($backup->local_path);
            }
            $backup->delete();
        } catch (\Throwable $exception) {
            $backup->update([
                'status' => BackupStatus::DeleteFailed,
                'failure_code' => $exception instanceof BackupOperationException ? $exception->failureCode : 'local_delete_failed',
                'failure_summary' => $exception instanceof BackupOperationException ? $exception->getMessage() : 'حذف نسخه پشتیبان ناموفق بود.',
            ]);
            throw $exception;
        }
    }
}
