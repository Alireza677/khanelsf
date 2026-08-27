<?php

namespace App\Services;

use App\Enums\ClientProjectCycleStatus;
use App\Enums\InvoiceStatus;
use App\Models\ClientProjectCycle;
use App\Models\Invoice;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ProjectCycleInvoiceGenerator
{
    public function __construct(private InvoiceNumberGenerator $numbers, private InvoiceTotalsCalculator $totals, private InvoiceItemSnapshot $snapshot) {}

    public function generate(ClientProjectCycle $cycle, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($cycle, $actor): Invoice {
            $cycle = ClientProjectCycle::query()->with('project.customer')->whereKey($cycle)->lockForUpdate()->firstOrFail();
            $existing = DB::table('invoice_cycle_claims')->where('client_project_cycle_id', $cycle->id)->value('invoice_id');
            if ($existing) {
                return Invoice::findOrFail($existing);
            }
            $activities = $cycle->activities()->with('project')->where('status', '!=', 'cancelled')->whereNotNull('total_amount')->orderBy('activity_date')->get();
            if ($activities->isEmpty()) {
                throw new DomainException('cycle_has_no_billable_activities');
            }
            $currencies = $activities->pluck('currency_snapshot')->filter()->map(fn ($value) => strtoupper($value))->unique();
            if ($currencies->count() !== 1 || $activities->contains(fn ($item) => blank($item->currency_snapshot))) {
                throw new DomainException('currency_inconsistency');
            }
            $values = $this->totals->calculate($activities->pluck('total_amount'));
            $invoice = Invoice::create([...$values, 'customer_id' => $cycle->project->customer_id, 'client_project_cycle_id' => $cycle->id, 'invoice_number' => $this->numbers->next($cycle->starts_at), 'period_start' => $cycle->starts_at, 'period_end' => $cycle->ends_at, 'currency' => $currencies->first(), 'status' => InvoiceStatus::Draft, 'created_by' => $actor?->id]);
            DB::table('invoice_cycle_claims')->insert(['client_project_cycle_id' => $cycle->id, 'invoice_id' => $invoice->id, 'created_at' => now()]);
            foreach ($activities as $activity) {
                DB::table('invoice_activity_claims')->insert(['client_project_activity_id' => $activity->id, 'invoice_id' => $invoice->id, 'created_at' => now()]);
                $invoice->items()->create($this->snapshot->from($activity));
            }
            $cycle->update(['status' => ClientProjectCycleStatus::Completed, 'completed_at' => $cycle->completed_at ?: now()]);

            return $invoice->load('items');
        }, 3);
    }
}
