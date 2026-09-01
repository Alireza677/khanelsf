<?php

namespace App\Jobs;

use App\Enums\BackupRestoreStatus;
use App\Models\BackupRestore;
use App\Services\BackupRestoreService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RestoreBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public readonly int $restoreId)
    {
        $this->timeout = (int) config('backup.restore_timeout', 7200);
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('cms-backup-restore'))->dontRelease()->expireAfter($this->timeout + 300)];
    }

    public function handle(BackupRestoreService $service): void
    {
        $restore = BackupRestore::query()->findOrFail($this->restoreId);
        if ($restore->status->terminal()) {
            return;
        }
        $service->run($restore);
    }
}
