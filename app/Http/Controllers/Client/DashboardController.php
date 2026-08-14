<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ClientProjectActivity;
use App\Services\ClientPortalDashboardStats;
use App\Services\ClientProjectActivityPresenter;
use App\Services\ClientServicesDashboardPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ClientPortalDashboardStats $stats,
        ClientProjectActivityPresenter $activityPresenter,
        ClientServicesDashboardPresenter $presenter,
    ): View {
        $customers = $request->attributes->get('portalCustomers');
        $customer = $request->attributes->get('portalCustomer');

        $customer?->load('users');
        $dashboardStats = $stats->forCustomer($customer, CarbonImmutable::now()->startOfMonth());
        $projects = $customer?->clientProjects()->latest('updated_at')->get() ?? collect();
        $dashboard = $presenter->present($projects, CarbonImmutable::now()->startOfMonth());
        $dashboardStats['worked_time'] = $dashboard['monthly']['used_time'];
        $projectFilter = $request->integer('project') ?: null;
        $range = in_array($request->query('range'), ['current', 'previous', 'all'], true) ? $request->query('range') : 'current';
        $activityQuery = ClientProjectActivity::query()->with('project:id,title,customer_id')
            ->when($customer, fn ($query) => $query->forCustomer($customer), fn ($query) => $query->whereRaw('1 = 0'))
            ->publishedForClient()
            ->when($projectFilter && $projects->contains('id', $projectFilter), fn ($query) => $query->forProject($projectFilter))
            ->when($range === 'current', fn ($query) => $query->inMonth(CarbonImmutable::now()->startOfMonth()))
            ->when($range === 'previous', fn ($query) => $query->inMonth(CarbonImmutable::now()->subMonth()->startOfMonth()));
        $recentActivities = $customer
            ? $activityQuery->latest('activity_date')->latest('id')->limit(8)->get()
                ->map(fn (ClientProjectActivity $activity): array => $activityPresenter->present($activity))
            : collect();
        $serviceRoutes = $request->routeIs('account.*')
            ? ['home' => 'account.services.index', 'projects' => 'account.projects.index', 'project_show' => 'account.projects.show']
            : ['home' => 'client.dashboard', 'projects' => 'client.projects.index', 'project_show' => 'client.projects.show'];

        return view('client.dashboard', [
            'customers' => $customers,
            'customer' => $customer,
            'primaryContact' => $customer?->primaryUser(),
            'dashboardStats' => $dashboardStats,
            'servicesDashboard' => $dashboard,
            'recentActivities' => $recentActivities,
            'activityFilters' => ['project' => $projectFilter, 'range' => $range],
            'serviceRoutes' => $serviceRoutes,
        ]);
    }
}
