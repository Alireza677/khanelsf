<?php

namespace App\Console\Commands;

use App\Models\Backup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupBackupTemporaryFiles extends Command
{
    protected $signature = 'backup:cleanup-orphans {--hours=}';

    protected $description = 'Remove unreferenced expired backup temporary directories';

    public function handle(): int
    {
        $root = storage_path('app/private/'.config('backup.temporary_prefix'));
        if (! File::isDirectory($root)) {
            $this->info('Removed 0 orphan backup directories.');

            return self::SUCCESS;
        }
        $cutoff = now()->subHours((int) ($this->option('hours') ?: config('backup.orphan_ttl_hours', 24)))->timestamp;
        $referenced = Backup::query()->whereNotNull('local_path')->pluck('local_path')
            ->map(fn (string $path): string => basename(dirname(str_replace('\\', '/', $path))))->all();
        $removed = 0;
        foreach (File::directories($root) as $directory) {
            if (! in_array(basename($directory), $referenced, true) && File::lastModified($directory) < $cutoff) {
                File::deleteDirectory($directory);
                $removed++;
            }
        }
        $this->info("Removed {$removed} orphan backup directories.");

        return self::SUCCESS;
    }
}
