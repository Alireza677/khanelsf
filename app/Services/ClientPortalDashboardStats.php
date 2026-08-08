<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use Carbon\CarbonImmutable;

class ClientPortalDashboardStats
{
    public function forCustomer(?Customer $customer, CarbonImmutable $month): array
    {
        if (! $customer) {
            return ['active_projects' => 0, 'published_activities' => 0, 'worked_minutes' => 0];
        }

        return [
            'active_projects' => ClientProject::query()->forCustomer($customer)->where('status', ClientProject::STATUS_ACTIVE)->count(),
            'published_activities' => ClientProjectActivity::query()->forCustomer($customer)->inMonth($month)->publishedForClient()->count(),
            'worked_minutes' => (int) ClientProjectActivity::query()->forCustomer($customer)->inMonth($month)
                ->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED)->sum('duration_minutes'),
        ];
    }
}
