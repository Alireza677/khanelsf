<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientPortalDashboardStats;
use App\Services\DurationFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ClientPortalDashboardStats $stats, DurationFormatter $durations): View
    {
        $customers = $request->attributes->get('portalCustomers');
        $customer = $request->attributes->get('portalCustomer');

        $customer?->load('users');
        $dashboardStats = $stats->forCustomer($customer, CarbonImmutable::now()->startOfMonth());
        $dashboardStats['worked_time'] = $durations->format($dashboardStats['worked_minutes']);

        return view('client.dashboard', [
            'customers' => $customers,
            'customer' => $customer,
            'primaryContact' => $customer?->primaryUser(),
            'dashboardStats' => $dashboardStats,
        ]);
    }
}
