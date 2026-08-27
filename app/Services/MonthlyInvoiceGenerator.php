<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class MonthlyInvoiceGenerator
{
    public function __construct(private BillableActivityQuery $billable, private InvoiceNumberGenerator $numbers, private InvoiceTotalsCalculator $totals, private InvoiceItemSnapshot $snapshot) {}

    public function generate(Customer $customer, CarbonImmutable $start, CarbonImmutable $end, ?User $actor = null): Invoice
    {
        if ($end->lt($start)) {
            throw new DomainException('invalid_period');
        }

        return DB::transaction(function () use ($customer, $start, $end, $actor): Invoice {
            $activities = $this->billable->for($customer, $start, $end)->lockForUpdate()->get();
            if ($activities->isEmpty()) {
                throw new DomainException('no_billable_activities');
            }
            $currencies = $activities->pluck('currency_snapshot')->filter()->map(fn ($value) => strtoupper($value))->unique();
            if ($currencies->count() !== 1 || $activities->contains(fn ($activity) => blank($activity->currency_snapshot))) {
                throw new DomainException('currency_inconsistency');
            }

            $calculated = $this->totals->calculate($activities->pluck('total_amount'));
            $invoice = Invoice::query()->create([...$calculated, 'customer_id' => $customer->getKey(), 'invoice_number' => $this->numbers->next($start), 'period_start' => $start, 'period_end' => $end, 'currency' => $currencies->first(), 'status' => InvoiceStatus::Draft, 'created_by' => $actor?->getKey()]);

            foreach ($activities as $activity) {
                try {
                    DB::table('invoice_activity_claims')->insert(['client_project_activity_id' => $activity->getKey(), 'invoice_id' => $invoice->getKey(), 'created_at' => now()]);
                } catch (QueryException $exception) {
                    throw new DomainException('activity_already_invoiced', previous: $exception);
                }
                $invoice->items()->create($this->snapshot->from($activity));
            }

            return $invoice->load('items');
        }, 3);
    }
}
