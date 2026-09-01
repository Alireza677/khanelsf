<?php

namespace App\Services;

use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Jobs\CreateBackupJob;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Support\Str;
use App\Models\BackupRestore;
use Illuminate\Support\Facades\Schema;

class BackupManager
{
    public function request(BackupType $type, User $user): Backup
    {
        $this->assertNoRestore();
        return $this->create([
            'type' => $type,
            'source' => BackupSource::Manual,
            'requested_by' => $user->id,
            'idempotency_key' => (string) Str::uuid(),
        ]);
    }

    public function hasActiveBackup(): bool
    {
        return Backup::query()->whereIn('status', [
            BackupStatus::Queued->value,
            BackupStatus::Creating->value,
        ])->exists();
    }

    private function create(array $attributes): Backup
    {
        $backup = Backup::query()->create([
            'status' => BackupStatus::Queued,
            ...$attributes,
        ]);
        CreateBackupJob::dispatch($backup->id)->onQueue((string) config('backup.queue', 'backups'));

        return $backup;
    }

    public function retry(Backup $backup): void
    {
        $this->assertNoRestore();
        if ($backup->status !== BackupStatus::Failed) {
            throw new BackupOperationException('backup_not_retryable', 'این نسخه پشتیبان قابل تلاش مجدد نیست.');
        }
        $backup->update(['status' => BackupStatus::Queued, 'failure_code' => null, 'failure_summary' => null]);
        CreateBackupJob::dispatch($backup->id)->onQueue((string) config('backup.queue', 'backups'));
    }

    private function assertNoRestore(): void
    {
        if (Schema::hasTable('backup_restores') && BackupRestore::query()->whereNotNull('active_lock')->exists()) {
            throw new BackupOperationException('restore_overlap', 'در زمان بازیابی امکان ایجاد یا تلاش مجدد Backup وجود ندارد.');
        }
    }
}
