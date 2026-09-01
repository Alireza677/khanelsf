<?php

namespace App\Services;

use App\Enums\BackupRestoreStatus;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Jobs\RestoreBackupJob;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\User;
use App\Support\BackupRestoreStart;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class BackupRestoreManager
{
    public function __construct(private readonly BackupRestoreProgressAccess $progressAccess) {}

    public function start(Backup $backup, User $user): BackupRestore
    {
        return $this->startWithProgress($backup, $user)->restore;
    }

    public function startWithProgress(Backup $backup, User $user): BackupRestoreStart
    {
        if (! $user->isAdmin() || ! $user->isActive()) {
            abort(403);
        }
        if ($backup->status !== BackupStatus::Completed || $backup->type !== BackupType::Full || ! $backup->isAvailable()) {
            throw new BackupOperationException('restore_backup_unavailable', 'این نسخه پشتیبان قابل بازیابی نیست.');
        }
        if (app(BackupManager::class)->hasActiveBackup()) {
            throw new BackupOperationException('backup_overlap', 'تا پایان عملیات Backup فعال، بازیابی قابل شروع نیست.');
        }

        $access = $this->progressAccess->issue();
        try {
            $restore = BackupRestore::query()->create([
                'backup_id' => $backup->id,
                'initiated_by' => $user->id,
                'status' => BackupRestoreStatus::Pending,
                'current_step' => BackupRestoreStatus::Pending->value,
                'active_lock' => 'installation',
                'progress_token_hash' => $access['hash'],
                'progress_token_expires_at' => $access['expires_at'],
                'metadata' => ['target_backup_uuid' => $backup->uuid],
            ]);
        } catch (QueryException $exception) {
            throw new BackupOperationException('restore_overlap', 'یک عملیات بازیابی فعال یا نیازمند recovery است.', $exception);
        }

        RestoreBackupJob::dispatch($restore->id)
            ->delay(now()->addSeconds((int) config('backup.restore_dispatch_delay_seconds', 3)))
            ->onQueue((string) config('backup.queue', 'backups'));

        return new BackupRestoreStart($restore, $access['token']);
    }

    public function hasBlockingRestore(): bool
    {
        return BackupRestore::query()->whereNotNull('active_lock')->exists();
    }

    public function recover(BackupRestore $failed): BackupRestore
    {
        $recovery = DB::transaction(function () use ($failed): BackupRestore {
            $locked = BackupRestore::query()->with('safetyBackup')->lockForUpdate()->findOrFail($failed->id);
            if ($locked->status !== BackupRestoreStatus::Failed || $locked->active_lock === null
                || ! $locked->safetyBackup?->isAvailable()) {
                throw new BackupOperationException('restore_recovery_unavailable', 'نسخه ایمنی معتبر برای Recovery این بازیابی در دسترس نیست.');
            }

            $locked->update(['active_lock' => null]);

            return BackupRestore::query()->create([
                'backup_id' => $locked->safety_backup_id,
                'safety_backup_id' => $locked->safety_backup_id,
                'initiated_by' => $locked->initiated_by,
                'status' => BackupRestoreStatus::Pending,
                'current_step' => BackupRestoreStatus::Pending->value,
                'active_lock' => 'installation',
                'metadata' => [
                    'target_backup_uuid' => $locked->safetyBackup->uuid,
                    'recovery_of_restore_uuid' => $locked->uuid,
                    'reuses_preserved_safety_backup' => true,
                ],
            ]);
        });

        RestoreBackupJob::dispatch($recovery->id)->onQueue((string) config('backup.queue', 'backups'));

        return $recovery;
    }
}
