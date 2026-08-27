<?php

namespace App\Services;

use App\Enums\ClientProjectCycleStatus;
use App\Events\ClientProjectCycleBecameOverdue;
use App\Models\ClientProjectCycle;
use DomainException;

final class RecalculateClientProjectCycle
{
    public function __construct(private ClientProjectCycleUsage $usage, private ProjectCycleInvoiceGenerator $invoices) {}

    public function handle(ClientProjectCycle $cycle): ClientProjectCycle
    {
        $cycle->refresh();
        $consumed = $this->usage->consumed($cycle);
        if ($consumed >= $cycle->allocated_minutes) {
            if (! in_array($cycle->status, [ClientProjectCycleStatus::Completed, ClientProjectCycleStatus::Invoiced], true)) {
                try {
                    $this->invoices->generate($cycle, auth()->user());
                } catch (DomainException $exception) {
                    if (! in_array($exception->getMessage(), ['cycle_has_no_billable_activities', 'currency_inconsistency'], true)) {
                        throw $exception;
                    }
                    // Work completion must remain durable even when financial
                    // snapshots need explicit admin repair before invoicing.
                    $cycle->update(['status' => ClientProjectCycleStatus::Completed, 'completed_at' => $cycle->completed_at ?: now()]);
                }
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
