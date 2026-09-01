<?php

namespace App\Services;

use App\Enums\BackupRestoreStatus;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use App\Models\BackupRestore;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class BackupRestoreService
{
    public function __construct(
        private readonly BackupRestoreArchive $archives,
        private readonly SafetyBackupService $safetyBackups,
        private readonly DatabaseRestoreService $database,
        private readonly BackupRestoreMigrationService $migrations,
        private readonly PersistentStorageRestoreService $storage,
        private readonly BackupRestoreHealthCheck $health,
        private readonly BackupMaintenanceMode $maintenance,
        private readonly BackupRestoreStateStore $state,
        private readonly BackupDatabaseBinaryPreflight $binaryPreflight,
    ) {}

    public function run(BackupRestore $restore): void
    {
        $restore->load(['backup', 'initiatedBy']);
        $artifact = null;
        $maintenanceEntered = false;
        $targetSnapshot = $restore->backup->getAttributes();
        $restoreSnapshot = $restore->getAttributes();
        $environmentHash = $this->environmentHash();
        $applicationKey = (string) config('app.key');
        $isRecovery = filled(data_get($restore->metadata, 'recovery_of_restore_uuid'));

        try {
            $this->checkpoint($restore->uuid, BackupRestoreStatus::Validating, [
                'restore_id' => $restore->id,
                'target_backup_id' => $restore->backup_id,
                'target_backup_uuid' => $restore->backup->uuid,
                'safety_backup_id' => $restore->safety_backup_id,
                'active_lock' => $restore->active_lock,
                'recovery' => $isRecovery,
            ]);
            $artifact = $this->archives->stage($restore->backup, $restore->uuid);
            $this->binaryPreflight->assertAvailable($restore->uuid);

            if ($isRecovery) {
                $safety = $restore->safetyBackup ?: $restore->backup;
            } else {
                $this->checkpoint($restore->uuid, BackupRestoreStatus::CreatingSafetyBackup);
                $safety = $this->safetyBackups->create($restore);
            }
            $safetySnapshot = $safety->getAttributes();
            $restore->update(['safety_backup_id' => $safety->id]);
            $this->state->write($restore->uuid, $isRecovery ? BackupRestoreStatus::Validating : BackupRestoreStatus::CreatingSafetyBackup, [
                'target_backup_uuid' => $restore->backup->uuid,
                'safety_backup_id' => $safety->id,
                'safety_backup_uuid' => $safety->uuid,
                'active_lock' => 'installation',
            ]);

            $this->checkpoint($restore->uuid, BackupRestoreStatus::EnteringMaintenance);
            $this->maintenance->enter();
            $maintenanceEntered = true;

            $this->checkpoint($restore->uuid, BackupRestoreStatus::RestoringDatabase);
            $this->database->restore($artifact->databaseDumpPath, $restore->uuid);

            $this->state->write($restore->uuid, BackupRestoreStatus::Migrating, [
                'target_backup_uuid' => $targetSnapshot['uuid'],
                'safety_backup_uuid' => $safetySnapshot['uuid'],
            ]);
            $this->migrations->migrate($artifact->manifest);
            $restore = $this->reconcileRecords($restoreSnapshot, $targetSnapshot, $safetySnapshot);
            $this->normalizeOperationalUserReferences();
            $this->checkpoint($restore->uuid, BackupRestoreStatus::Migrating);

            $this->checkpoint($restore->uuid, BackupRestoreStatus::RestoringStorage);
            $this->storage->replace($artifact, $restore->uuid);

            $this->checkpoint($restore->uuid, BackupRestoreStatus::HealthCheck);
            if (! hash_equals($environmentHash, $this->environmentHash()) || ! hash_equals($applicationKey, (string) config('app.key'))) {
                throw new BackupOperationException('restore_environment_changed', 'تنظیمات محیطی حین بازیابی تغییر کرده است.');
            }
            $this->health->assertHealthy();
            $this->maintenance->leave();
            $maintenanceEntered = false;
            $this->checkpoint($restore->uuid, BackupRestoreStatus::Completed, [], releaseLock: true);
        } catch (Throwable $exception) {
            $code = $exception instanceof BackupOperationException ? $exception->failureCode : 'restore_failed';
            $message = $exception instanceof BackupOperationException ? $exception->getMessage() : 'بازیابی نسخه پشتیبان ناموفق بود.';
            $this->checkpoint($restore->uuid, BackupRestoreStatus::Failed, [
                'failure_code' => $code,
                'error_message' => $message,
                'maintenance_active' => $maintenanceEntered,
            ], releaseLock: ! $maintenanceEntered);
            Log::error('CMS restore failed.', [
                'restore_uuid' => $restore->uuid,
                'failure_code' => $code,
                'exception' => $exception::class,
                'maintenance_active' => $maintenanceEntered,
            ]);
            throw $exception;
        } finally {
            if ($artifact instanceof BackupRestoreArtifact) {
                File::deleteDirectory($artifact->stagingPath);
            }
        }
    }

    private function reconcileRecords(array $restoreSnapshot, array $targetSnapshot, array $safetySnapshot): BackupRestore
    {
        $target = $this->reconcileBackup($targetSnapshot);
        $safety = $this->reconcileBackup($safetySnapshot);
        $initiatedBy = is_numeric($restoreSnapshot['initiated_by'] ?? null)
            && User::query()->whereKey($restoreSnapshot['initiated_by'])->exists()
                ? (int) $restoreSnapshot['initiated_by']
                : null;

        return BackupRestore::query()->updateOrCreate(
            ['uuid' => $restoreSnapshot['uuid']],
            [
                'backup_id' => $target->id,
                'safety_backup_id' => $safety->id,
                'initiated_by' => $initiatedBy,
                'status' => BackupRestoreStatus::Migrating,
                'current_step' => BackupRestoreStatus::Migrating->value,
                'active_lock' => 'installation',
                'metadata' => ['target_backup_uuid' => $target->uuid, 'safety_backup_uuid' => $safety->uuid],
                'started_at' => $restoreSnapshot['started_at'] ?? now(),
                'finished_at' => null,
            ],
        );
    }

    private function reconcileBackup(array $snapshot): Backup
    {
        unset($snapshot['id'], $snapshot['created_at'], $snapshot['updated_at']);
        if (is_numeric($snapshot['requested_by'] ?? null) && ! User::query()->whereKey($snapshot['requested_by'])->exists()) {
            $snapshot['requested_by'] = null;
        }

        return Backup::query()->updateOrCreate(['uuid' => $snapshot['uuid']], $snapshot);
    }

    private function normalizeOperationalUserReferences(): void
    {
        $userIds = User::query()->pluck('id');
        $backupQuery = Backup::query()->whereNotNull('requested_by');
        $restoreQuery = BackupRestore::query()->whereNotNull('initiated_by');
        if ($userIds->isEmpty()) {
            $backupQuery->update(['requested_by' => null]);
            $restoreQuery->update(['initiated_by' => null]);

            return;
        }
        $backupQuery->whereNotIn('requested_by', $userIds)->update(['requested_by' => null]);
        $restoreQuery->whereNotIn('initiated_by', $userIds)->update(['initiated_by' => null]);
    }

    private function checkpoint(
        string $uuid,
        BackupRestoreStatus $status,
        array $extra = [],
        bool $releaseLock = false,
    ): void {
        if ($releaseLock) {
            $extra['active_lock'] = null;
        }
        $this->state->write($uuid, $status, $extra);
        try {
            $attributes = [
                'status' => $status,
                'current_step' => $status->value,
                'started_at' => $status === BackupRestoreStatus::Validating ? now() : BackupRestore::query()->where('uuid', $uuid)->value('started_at'),
                'finished_at' => $status->terminal() ? now() : null,
            ];
            if (isset($extra['failure_code'])) {
                $attributes['failure_code'] = $extra['failure_code'];
                $attributes['error_message'] = $extra['error_message'];
            }
            if ($releaseLock) {
                $attributes['active_lock'] = null;
            }
            if ($status->terminal()) {
                $attributes['progress_token_expires_at'] = now()->addMinutes(
                    (int) config('backup.progress_token_terminal_ttl_minutes', 5),
                );
            }
            BackupRestore::query()->where('uuid', $uuid)->update($attributes);
        } catch (Throwable) {
            // The private checkpoint remains authoritative while the imported DB is unavailable.
        }
    }

    private function environmentHash(): string
    {
        $path = base_path('.env');

        return is_file($path) ? (string) hash_file('sha256', $path) : 'missing';
    }
}
