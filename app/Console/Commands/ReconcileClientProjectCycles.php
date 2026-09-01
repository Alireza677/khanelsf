<?php

namespace App\Console\Commands;

use App\Models\ClientProject;
use App\Services\ClientProjectCycleReconciler;
use App\Support\PersianDate;
use Illuminate\Console\Command;

final class ReconcileClientProjectCycles extends Command
{
    protected $signature = 'client-project-cycles:reconcile
        {projectId? : Reconcile one ClientProject ID}
        {--dry-run : Report proposed changes without writing data}
        {--apply : Explicitly apply allowed, unprotected changes}';

    protected $description = 'Audit or reconcile activity attribution against current ClientProject contract-cycle boundaries';

    public function handle(ClientProjectCycleReconciler $reconciler): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->components->error('Use either --dry-run or --apply, not both.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');
        $query = ClientProject::query()->orderBy('id');
        if ($projectId = $this->argument('projectId')) {
            $query->whereKey((int) $projectId);
        }
        $projects = $query->get();
        if ($projects->isEmpty()) {
            $this->components->error('No matching ClientProject was found.');

            return self::FAILURE;
        }

        $this->components->info($apply ? 'APPLY mode' : 'DRY RUN — no data will be changed');
        foreach ($projects as $project) {
            $before = $reconciler->plan($project);
            $this->renderPlan($before);

            if ($apply) {
                $reconciler->reconcile($project);
                $this->components->info("Project {$project->id} reconciliation applied.");
            }
        }

        return self::SUCCESS;
    }

    /** @param array<string, mixed> $plan */
    private function renderPlan(array $plan): void
    {
        $project = $plan['project'];
        $this->newLine();
        $this->components->twoColumnDetail(
            "Project {$project['id']}",
            $project['title'].' · start '.($project['start_date'] ?? '—').' · end '.($project['end_date'] ?? '—'),
        );
        $this->table([
            'Activity', 'Raw date', 'Persian date', 'Old cycle', 'Proposed cycle', 'Claimed', 'Protected', 'Allowed', 'Reason',
        ], collect($plan['activities'])->map(fn (array $row): array => [
            $row['activity_id'],
            $row['activity_date'],
            PersianDate::date($row['activity_date']),
            ($row['old_cycle_id'] ?? '—').' '.($row['old_cycle'] ?? ''),
            ($row['proposed_cycle_id'] ?? 'new/—').' '.($row['proposed_cycle'] ?? ''),
            $row['claimed'] ? 'yes' : 'no',
            $row['protected'] ? 'yes' : 'no',
            $row['allowed'] ? 'yes' : 'no',
            $row['reason'],
        ])->all());

        $before = $plan['current_cycle_before'];
        $after = $plan['current_cycle_after'];
        $this->table(['Current-cycle metric', 'Before', 'After proposed reconciliation'], [[
            'Usage minutes',
            $before ? $before['consumed_minutes'].' · '.$before['period'] : 'no current cycle',
            $after ? $after['consumed_minutes'].' · '.$after['period'] : 'no current cycle',
        ]]);
    }
}
