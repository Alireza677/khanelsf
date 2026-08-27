<?php

namespace App\Services;

use App\Models\ClientProjectActivity;
use App\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class BillableActivityQuery
{
    public function for(Customer $customer, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return ClientProjectActivity::query()->with('project')
            ->whereHas('project', fn (Builder $query) => $query->where('customer_id', $customer->getKey()))
            ->whereBetween('activity_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED)
            ->whereNotNull('total_amount')->where('total_amount', '>=', 0)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('invoice_activity_claims')->whereColumn('invoice_activity_claims.client_project_activity_id', 'client_project_activities.id'))
            ->orderBy('activity_date')->orderBy('id');
    }
}
