<?php

namespace App\Console\Commands;

use App\Services\LegacyGalleryMigrationService;
use Illuminate\Console\Command;
use Throwable;

final class MigrateLegacyGallery extends Command
{
    protected $signature = 'cms:gallery-migration:migrate {--gallery= : Required legacy Gallery ID} {--apply : Apply the reviewed plan}';
    protected $description = 'Dry-run or apply one reviewed legacy Gallery migration';

    public function handle(LegacyGalleryMigrationService $migration): int
    {
        $id = $this->option('gallery');
        if ($id === null || ! ctype_digit((string) $id) || (int) $id < 1) {
            $this->error('--gallery=<id> is required and must be a positive integer.');
            return self::INVALID;
        }

        $plan = $migration->plan((int) $id);
        $this->info($this->option('apply') ? 'APPLY MODE' : 'DRY RUN');
        $this->line('Gallery: '.($plan['gallery'] ?? '#'.$id.' (not found)'));
        $this->line('Target: '.($plan['target'] ?? '-'));
        $this->line('Final target URL: '.($plan['target_url'] ?? '-'));
        $this->newLine();
        $this->line('Actions:');
        foreach ($plan['actions'] as $action => $value) {
            $this->line(strtoupper($action).': '.$value);
        }
        $this->line('WARNINGS: '.($plan['warnings'] ? implode(', ', $plan['warnings']) : 'NONE'));
        $this->line('BLOCKERS: '.($plan['blockers'] ? implode(', ', $plan['blockers']) : 'NONE'));

        if (! $this->option('apply')) {
            return $plan['blockers'] === [] ? self::SUCCESS : self::FAILURE;
        }
        if ($plan['blockers'] !== []) {
            $this->error('Apply aborted before writes.');
            return self::FAILURE;
        }

        try {
            $result = $migration->apply((int) $id);
            $this->newLine();
            $this->info('Migration completed.');
            foreach ($result as $key => $value) {
                $this->line(strtoupper($key).': '.$value);
            }
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Migration failed: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
