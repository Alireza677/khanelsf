<?php

namespace App\Services;

use App\Enums\ClientProjectCycleStatus;
use App\Enums\InvoiceStatus;
use App\Events\InvoiceIssued;
use App\Jobs\GenerateInvoicePdf;
use App\Models\Invoice;
use DomainException;
use Illuminate\Support\Facades\DB;

final class InvoiceLifecycle
{
    public function __construct(private InvoiceTotalsCalculator $totals) {}

    public function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->whereKey($invoice)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== InvoiceStatus::Draft || ! $invoice->items()->exists()) {
                throw new DomainException('invoice_cannot_be_issued');
            }
            $itemIds = $invoice->items()->pluck('client_project_activity_id');
            $claimedIds = DB::table('invoice_activity_claims')->where('invoice_id', $invoice->getKey())->pluck('client_project_activity_id');
            if ($itemIds->contains(null) || $itemIds->sort()->values()->all() !== $claimedIds->sort()->values()->all()) {
                throw new DomainException('invoice_activity_claim_conflict');
            }
            $values = $this->totals->calculate($invoice->items()->pluck('total_amount'), $invoice->discount_amount, $invoice->tax_amount);
            $invoice->forceFill([...$values, ...app(InvoicePartySnapshot::class)->capture($invoice), 'status' => InvoiceStatus::Issued, 'issued_at' => now(), 'pdf_status' => 'pending'])->save();
            if ($invoice->cycle) {
                $invoice->cycle->update(['status' => ClientProjectCycleStatus::Invoiced, 'invoiced_at' => $invoice->issued_at]);
            }

            DB::afterCommit(function () use ($invoice) {
                event(new InvoiceIssued($invoice->fresh()));
                GenerateInvoicePdf::dispatch($invoice->id);
            });

            return $invoice->refresh();
        });
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->whereKey($invoice)->lockForUpdate()->firstOrFail();
            if ($invoice->status !== InvoiceStatus::Issued) {
                throw new DomainException('invoice_cannot_be_paid');
            }
            $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

            return $invoice->refresh();
        });
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Issued], true)) {
            throw new DomainException('invoice_cannot_be_cancelled');
        }

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->whereKey($invoice)->lockForUpdate()->firstOrFail();
            if (! in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Issued], true)) {
                throw new DomainException('invoice_cannot_be_cancelled');
            }
            $invoice->update(['status' => InvoiceStatus::Cancelled]);
            DB::table('invoice_activity_claims')->where('invoice_id', $invoice->getKey())->delete();
            DB::table('invoice_cycle_claims')->where('invoice_id', $invoice->getKey())->delete();
            if ($invoice->cycle) {
                $invoice->cycle->update(['status' => ClientProjectCycleStatus::Completed, 'invoiced_at' => null]);
            }

            return $invoice->refresh();
        });
    }
}
