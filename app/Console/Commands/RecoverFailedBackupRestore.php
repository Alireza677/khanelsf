<?php

namespace App\Console\Commands;

use App\Models\BackupRestore;
use App\Services\BackupRestoreManager;
use Illuminate\Console\Command;

class RecoverFailedBackupRestore extends Command
{
    protected $signature = 'backup:restore:recover {uuid} {--force : Confirm recovery to the preserved safety backup}';

    protected $description = 'Queue a controlled restore of the safety backup for a locked failed restore';

    public function handle(BackupRestoreManager $manager): int
    {
        if (! $this->option('force')) {
            $this->error('Use --force only after confirming recovery to the linked safety backup.');

            return self::FAILURE;
        }
        $failed = BackupRestore::query()->where('uuid', $this->argument('uuid'))->first();
        if (! $failed) {
            $this->error('Restore record was not found.');

            return self::FAILURE;
        }

        $recovery = $manager->recover($failed);
        $this->info('Safety-backup recovery queued: '.$recovery->uuid);

        return self::SUCCESS;
    }
}
