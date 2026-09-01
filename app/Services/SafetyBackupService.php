<?php

namespace App\Services;

use App\Enums\BackupSource;
use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use App\Models\BackupRestore;
use Illuminate\Support\Facades\File;

class SafetyBackupService
{
    public function __construct(
        private readonly BackupArchiveBuilder $builder,
        private readonly LocalBackupStorage $storage,
    ) {}

    public function create(BackupRestore $restore): Backup
    {
        $backup = Backup::query()->create([
            'type' => BackupType::Full,
            'source' => BackupSource::Automatic,
            'status' => BackupStatus::Creating,
            'requested_by' => $restore->initiated_by,
            'idempotency_key' => 'pre-restore:'.$restore->uuid,
            'attempt' => 1,
            'started_at' => now(),
            'metadata' => [
                'purpose' => 'automatic_pre_restore',
                'restore_uuid' => $restore->uuid,
                'target_backup_uuid' => $restore->backup->uuid,
            ],
        ]);

        $artifact = null;
        try {
            $artifact = $this->builder->build($backup);
            $stored = $this->storage->store($backup, $artifact['path']);
            $backup->update([
                'archive_name' => basename($artifact['path']),
                'archive_format' => 'zip',
                'archive_version' => 1,
                'manifest_version' => 1,
                'size_bytes' => $artifact['size'],
                'checksum_algorithm' => 'sha256',
                'checksum' => $artifact['checksum'],
                'local_disk' => $stored['disk'],
                'local_path' => $stored['path'],
                'metadata' => [...($backup->metadata ?? []), 'manifest' => $artifact['metadata']],
                'status' => BackupStatus::Completed,
                'finished_at' => now(),
            ]);
            @rmdir(dirname($artifact['path']));

            return $backup->fresh();
        } catch (\Throwable $exception) {
            if (is_array($artifact) && isset($artifact['path'])) {
                File::delete($artifact['path']);
            }
            $backup->update([
                'status' => BackupStatus::Failed,
                'failure_code' => 'pre_restore_backup_failed',
                'failure_summary' => 'ایجاد نسخه پشتیبان ایمنی ناموفق بود.',
                'finished_at' => now(),
            ]);
            throw new BackupOperationException('pre_restore_backup_failed', 'ایجاد نسخه پشتیبان ایمنی ناموفق بود؛ بازیابی شروع نشد.', $exception);
        }
    }
}
