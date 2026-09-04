<?php

namespace App\Services;

use App\Enums\ClientProjectCycleStatus;
use App\Events\ClientProjectCycleBecameOverdue;
use App\Models\ClientProjectCycle;

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
            $cycle->update(['status' => $cycle->ends_at->isPast() ? ClientProjectCycleStatus::Overdue : ClientProjectCycleStatus::Active, 'completed_at' => null]);
        }

        return $cycle->refresh();
    }

    public function refreshOverdue(): int
    {
        $cycles = ClientProjectCycle::query()->where('status', ClientProjectCycleStatus::Active)->whereDate('ends_at', '<', today())->get();
        foreach ($cycles as $cycle) {
            $cycle->update(['status' => ClientProjectCycleStatus::Overdue]);
            event(new ClientProjectCycleBecameOverdue($cycle));
        }

        return $cycles->count();
    }
}
