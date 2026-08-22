<?php

namespace App\Jobs;

use App\Enums\BackupStatus;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use App\Services\BackupArchiveBuilder;
use App\Services\LocalBackupRetentionService;
use App\Services\LocalBackupStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public function __construct(public readonly int $backupId)
    {
        $this->timeout = (int) config('backup.timeout', 3600);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('cms-backup'))->releaseAfter(60)->expireAfter($this->timeout + 60)];
    }

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(BackupArchiveBuilder $builder, LocalBackupStorage $storage, LocalBackupRetentionService $retention): void
    {
        $backup = Backup::query()->findOrFail($this->backupId);
        if ($backup->status === BackupStatus::Completed) {
            return;
        }

        $backup->increment('attempt');
        $backup->update([
            'status' => BackupStatus::Creating,
            'started_at' => $backup->started_at ?? now(),
            'finished_at' => null,
            'failure_code' => null,
            'failure_summary' => null,
        ]);

        $artifact = null;
        try {
            $artifact = $builder->build($backup->fresh());
            $stored = $storage->store($backup, $artifact['path']);
            $backup->update([
                'archive_name' => basename($artifact['path']),
                'size_bytes' => $artifact['size'],
                'checksum_algorithm' => 'sha256',
                'checksum' => $artifact['checksum'],
                'local_disk' => $stored['disk'],
                'local_path' => $stored['path'],
                'metadata' => array_merge($backup->metadata ?? [], ['manifest' => $artifact['metadata']]),
                'status' => BackupStatus::Completed,
                'finished_at' => now(),
            ]);
            @rmdir(dirname($artifact['path']));
            $retention->prune();
        } catch (Throwable $exception) {
            if (is_array($artifact) && isset($artifact['path']) && is_file($artifact['path'])) {
                File::delete($artifact['path']);
                @rmdir(dirname($artifact['path']));
            }
            $code = $exception instanceof BackupOperationException ? $exception->failureCode : 'backup_failed';
            $summary = $exception instanceof BackupOperationException ? $exception->getMessage() : 'ایجاد نسخه پشتیبان ناموفق بود.';
            $backup->update([
                'status' => BackupStatus::Failed,
                'failure_code' => $code,
                'failure_summary' => $summary,
                'finished_at' => now(),
            ]);
            Log::error('Backup operation failed.', ['backup_uuid' => $backup->uuid, 'failure_code' => $code, 'exception' => $exception::class]);

            if (in_array($code, ['dump_binary_missing', 'database_driver_unsupported'], true)) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
