<?php

namespace App\Console\Commands;

use App\Services\ClientProjectCleanupPlan;
use App\Services\TestClientProjectCleanup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class CleanupTestClientProject extends Command
{
    protected $signature = 'client-project:cleanup-test
        {project_id : Exactly one explicitly selected test ClientProject ID}
        {--dry-run : Read-only inspection (also the default)}
        {--execute : Explicitly delete the reviewed test project and its exclusive nonfinancial records}
        {--confirm-project= : Retype the exact project ID}
        {--confirm-customer= : Retype the customer ID shown by dry-run}
        {--fingerprint= : Exact SHA-256 fingerprint from the reviewed dry-run}
        {--delete-files : After commit, unlink only verified exclusive private attachments; default is retain}
        {--verify-manifest= : Read-only post-deletion verification using the printed signed manifest path}';

    protected $description = 'Safely inspect or explicitly clean up ONE test ClientProject; financial history always blocks deletion';

    public function handle(): int
    {
        try {
            $id = ClientProjectCleanupPlan::projectId($this->argument('project_id'));
            $execute = (bool) $this->option('execute');
            $verify = $this->option('verify-manifest');
            if (($execute && ($this->option('dry-run') || $verify)) || ($this->option('delete-files') && ! $execute)) {
                $this->components->error('Conflicting modes. --delete-files requires --execute; verification and dry-run never execute deletion.');

                return self::INVALID;
            }
            if ($execute && (! $this->option('confirm-project') || ! $this->option('confirm-customer') || ! $this->option('fingerprint'))) {
                $this->components->error('Execution requires --confirm-project, --confirm-customer and --fingerprint from a reviewed dry-run.');

                return self::INVALID;
            }
            $cleanup = new TestClientProjectCleanup(DB::connection());
            if ($verify) {
                $result = $cleanup->verify($id, $verify);
                $this->table(['Remaining target', 'Exact predicate'], array_map(fn ($row) => [$row['table'], json_encode($row['where'])], $result['remaining']));
                $this->table(['Private file', 'Current state'], array_map(fn ($row) => [$row['path'], $row['state']], $result['files']));
                $this->line('Shared customer present: '.($result['customer_present'] ? 'yes' : 'NO'));
                $ok = $result['remaining'] === [] && $result['customer_present'] && ! $result['files_pending'];
                $this->components->info($ok ? 'Verified: all captured database targets are absent; expected file state matches.' : 'Verification incomplete. No changes were made.');

                return $ok ? self::SUCCESS : self::FAILURE;
            }

            $plan = DB::connection()->transaction(fn () => $cleanup->inspect($id), 1);
            if (! $plan) {
                $this->components->info("Project {$id} is absent. No changes made. Use its signed manifest to verify previously captured dependents.");

                return $execute ? self::FAILURE : self::SUCCESS;
            }
            $this->renderPlan($plan);
            if ($plan->blockers !== []) {
                $this->components->error('BLOCKED — no deletion is permitted.');
                foreach ($plan->blockers as $blocker) {
                    $this->line(' - '.$blocker);
                }

                return self::FAILURE;
            }
            if (! $execute) {
                $this->components->info('DRY RUN — database and files are unchanged. No manifest was written.');

                return self::SUCCESS;
            }
            $plan->confirm($this->option('confirm-project'), $this->option('confirm-customer'), $this->option('fingerprint'));
            $result = $cleanup->execute($id, $this->option('confirm-project'), $this->option('confirm-customer'), $this->option('fingerprint'), (bool) $this->option('delete-files'),
                fn (string $path) => $this->line('Prepared signed manifest (verify outcome if interrupted): '.$path));
            $this->components->info("Database deletion committed for project {$id} ONLY.");
            $this->line('Signed verification/recovery manifest: '.$result['manifest']);
            $this->table(['Private file', 'Outcome'], array_map(fn ($row) => [$row['path'], $row['state']], $result['file_results']));
            $failedFiles = collect($result['file_results'])->contains(fn ($row) => str_starts_with($row['state'], 'retained:'));
            if ($failedFiles) {
                $this->components->warn('Database deletion is committed, but some files were retained. Preserve the manifest and verify; do not repeat database deletion.');
            }

            return $failedFiles ? self::FAILURE : self::SUCCESS;
        } catch (\Throwable $error) {
            $this->components->error($error->getMessage());

            return self::FAILURE;
        }
    }

    private function renderPlan(ClientProjectCleanupPlan $plan): void
    {
        $this->table(['Selected identity', 'Value'], [
            ['Project ID', $plan->project['id']], ['Project title', $plan->project['title']],
            ['Customer ID', $plan->project['customer_id']], ['Customer name', $plan->customer['display_name'] ?? 'MISSING'],
            ['Delivery mode', $plan->project['delivery_mode']], ['Database connection', DB::connection()->getName()],
            ['Database name', DB::connection()->getDatabaseName()],
        ]);
        $this->table(['Exclusively owned table / candidate scope', 'Count'], array_map(fn ($table) => [$table, $table === 'client_projects' ? 1 : count($plan->rows[$table] ?? [])], array_keys(ClientProjectCleanupPlan::KEYS)));
        $this->table(['Protected financial table', 'Count', 'Record / invoice IDs'], collect($plan->financial)->map(fn ($records, $table) => [
            $table, count($records), implode(', ', array_unique(array_map(fn ($row) => (string) ($row['id'] ?? $row['invoice_id']), $records))),
        ])->values()->all());
        $paid = collect($plan->financial['invoices'])->where('status', 'paid')->count();
        $this->line("Related paid invoices: {$paid}. This source has no separate service-invoice payments table; shop orders are shared/unrelated and never deleted.");
        $this->table(['Related invoice', 'Customer', 'Status', 'Paid at'], array_map(fn ($row) => [$row['id'], $row['customer_id'], $row['status'], $row['paid_at'] ?? '—'], $plan->financial['invoices']));
        $this->table(['Attachment ID', 'Delivery', 'Private disk', 'Exact path', 'References'], array_map(fn ($row) => [$row['id'], $row['client_work_item_delivery_id'], $row['disk'], $row['path'], $row['path_references']], $plan->rows['client_delivery_files']));
        $this->line('Fingerprint: '.$plan->fingerprint());
        $this->line('Never deleted: Customer, User, Service, ServiceOffering, OfferingVersion, DeliverableType, shared media, financial records or other projects.');
        $this->line('Physical files are retained unless --execute AND --delete-files are explicitly supplied.');
    }
}
