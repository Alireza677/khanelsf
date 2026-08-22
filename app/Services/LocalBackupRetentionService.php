<?php

namespace App\Services;

use App\Enums\BackupStatus;
use App\Models\Backup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LocalBackupRetentionService
{
    public function __construct(private readonly BackupDeletionService $deletion) {}

    public function prune(): void
    {
        $keep = max(1, (int) config('backup.local_retention_count', 3));
        $candidates = Backup::query()
            ->where('status', BackupStatus::Completed->value)
            ->whereNotNull('local_disk')->whereNotNull('local_path')
            ->latest('finished_at')->latest('id')->take(1000)->get();

        $available = $candidates->filter(function (Backup $backup): bool {
            if (Storage::disk($backup->local_disk)->exists($backup->local_path)) {
                return true;
            }
            $backup->update([
                'status' => BackupStatus::Failed,
                'failure_code' => 'backup_file_missing',
                'failure_summary' => 'فایل نسخه پشتیبان در دسترس نیست.',
            ]);

            return false;
        });

        foreach ($available->slice($keep) as $backup) {
            try {
                $this->deletion->delete($backup);
            } catch (\Throwable $exception) {
                Log::error('Local backup retention failed.', ['backup_uuid' => $backup->uuid, 'exception' => $exception::class]);
            }
        }
    }
}
