<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\ClientProjectActivity;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DraftInvoiceItems
{
    public function __construct(private InvoiceItemSnapshot $snapshot, private InvoiceTotalsCalculator $totals) {}

    public function add(Invoice $invoice, ClientProjectActivity $activity): InvoiceItem
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new DomainException('invoice_not_draft');
        }
        if ($invoice->client_project_cycle_id && $activity->client_project_cycle_id !== $invoice->client_project_cycle_id) {
            throw new DomainException('activity_not_in_invoice_cycle');
        }
        $valid = app(BillableActivityQuery::class)->for($invoice->customer, $invoice->period_start, $invoice->period_end)->whereKey($activity)->exists();
        if (! $valid || strtoupper((string) $activity->currency_snapshot) !== $invoice->currency) {
            throw new DomainException('activity_not_billable');
        }

        return DB::transaction(function () use ($invoice, $activity): InvoiceItem {
            DB::table('invoice_activity_claims')->insert(['client_project_activity_id' => $activity->id, 'invoice_id' => $invoice->id, 'created_at' => now()]);
            $item = $invoice->items()->create($this->snapshot->from($activity));
            $this->refreshTotals($invoice);

            return $item;
        });
    }

    public function remove(InvoiceItem $item): void
    {
        $invoice = $item->invoice;
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new DomainException('invoice_not_draft');
        }
        DB::transaction(function () use ($item, $invoice): void {
            $item->delete();
            $this->refreshTotals($invoice);
        });
    }

    private function refreshTotals(Invoice $invoice): void
    {
        $invoice->update($this->totals->calculate($invoice->items()->pluck('total_amount'), $invoice->discount_amount, $invoice->tax_amount));
    }
}
