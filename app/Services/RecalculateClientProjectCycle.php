<?php

namespace App\Services;

use App\Enums\ClientProjectCycleStatus;
use App\Events\ClientProjectCycleBecameOverdue;
use App\Models\ClientProjectCycle;
use Illuminate\Support\Facades\DB;

final class RecalculateClientProjectCycle
{
    public function __construct(private ClientProjectCycleUsage $usage) {}

    public function handle(ClientProjectCycle $cycle): ClientProjectCycle
    {
        $cycle->refresh();
        $consumed = $this->usage->consumed($cycle);
        if ($consumed >= $cycle->allocated_minutes) {
            if (! in_array($cycle->status, [ClientProjectCycleStatus::Completed, ClientProjectCycleStatus::Invoiced], true)) {
                // Reaching the contractual allocation completes the quota, but
                // must not freeze the cycle: overage can still be recorded until
                // an admin explicitly creates/issues its invoice.
                $cycle->update(['status' => ClientProjectCycleStatus::Completed, 'completed_at' => $cycle->completed_at ?: now()]);
            }
        } elseif ($cycle->status !== ClientProjectCycleStatus::Invoiced) {
            $cycle->update(['status' => $cycle->ends_at->lte(today()) ? ClientProjectCycleStatus::Overdue : ClientProjectCycleStatus::Active, 'completed_at' => null]);
        }

        return $cycle->refresh();
    }

    public function refreshOverdue(): int
    {
        $query = ClientProjectCycle::query()
            ->where('status', ClientProjectCycleStatus::Active)
            ->whereDate('ends_at', '<=', today())
            ->whereDoesntHave('invoice')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('invoice_cycle_claims')
                ->whereColumn('invoice_cycle_claims.client_project_cycle_id', 'client_project_cycles.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('client_project_activities as claimed_cycle_activities')
                ->join('invoice_activity_claims', 'invoice_activity_claims.client_project_activity_id', '=', 'claimed_cycle_activities.id')
                ->whereColumn('claimed_cycle_activities.client_project_cycle_id', 'client_project_cycles.id'));
        $count = 0;
        foreach ($query->get() as $cycle) {
            $count += DB::transaction(function () use ($query, $cycle): int {
                $cycle = (clone $query)->whereKey($cycle)->lockForUpdate()->first();
                if (! $cycle) {
                    return 0;
                }

                $cycle = $this->handle($cycle);
                if ($cycle->status !== ClientProjectCycleStatus::Overdue) {
                    return 0;
                }

                event(new ClientProjectCycleBecameOverdue($cycle));

                return 1;
            }, 3);
        }

        return $count;
    }
}
