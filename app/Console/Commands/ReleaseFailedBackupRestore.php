<?php

namespace App\Console\Commands;

use App\Enums\BackupRestoreStatus;
use App\Models\BackupRestore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ReleaseFailedBackupRestore extends Command
{
    protected $signature = 'backup:restore:release {uuid} {--force : Confirm that manual recovery/verification is complete} {--bring-up : Leave maintenance mode}';

    protected $description = 'Release the installation lock after an operator has manually recovered a failed destructive restore';

    public function handle(): int
    {
        if (! $this->option('force')) {
            $this->error('Use --force only after restoring/verifying the preserved safety backup.');
            return self::FAILURE;
        }
        $restore = BackupRestore::query()->where('uuid', $this->argument('uuid'))->first();
        if (! $restore || $restore->status !== BackupRestoreStatus::Failed || $restore->active_lock === null) {
            $this->error('A locked failed restore with this UUID was not found.');
            return self::FAILURE;
        }
        if ($this->option('bring-up') && Artisan::call('up') !== 0) {
            $this->error('The application could not leave maintenance mode.');
            return self::FAILURE;
        }
        $restore->update(['active_lock' => null]);
        $this->info('Restore lock released after explicit operator confirmation.');

        return self::SUCCESS;
    }
}
