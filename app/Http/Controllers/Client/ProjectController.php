<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientProjectAccess;
use App\Services\ClientProjectActivityPresenter;
use App\Services\ClientProjectCycleUsage;
use App\Services\ClientProjectMonthlyTimeService;
use App\Services\ClientProjectPresenter;
use App\Services\DurationFormatter;
use App\Services\MonthResolver;
use App\Support\PersianDate;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request, ClientProjectAccess $access, ClientProjectPresenter $presenter): View
    {
        $customer = $request->attributes->get('portalCustomer');
        $projects = $access->paginateFor($customer);
        $projects->through(fn ($project): array => $presenter->present($project));

        return view('client.projects.index', [
            'projects' => $projects,
            'serviceRoutes' => $this->serviceRoutes($request),
        ]);
    }

    public function show(
        Request $request,
        int $project,
        ClientProjectAccess $access,
        ClientProjectPresenter $presenter,
        ClientProjectActivityPresenter $activityPresenter,
        ClientProjectMonthlyTimeService $timeService,
        ClientProjectCycleUsage $cycleUsage,
        DurationFormatter $durations,
        MonthResolver $months,
    ): View {
        $project = $access->findFor($request->attributes->get('portalCustomer'), $project);
        Gate::forUser($request->user('client'))->authorize('view', $project);
        $month = $months->resolveRange($request->query('month'));
        $activities = $project->activities()->with('cycle')->publishedForClient()->inMonth($month['start'], $month['end'])
            ->latest('activity_date')->latest('id')->paginate(10)->withQueryString();
        $activities->through(fn ($activity): array => $activityPresenter->present($activity));
        $summary = $timeService->summarize($project, $month['start'], $month['end']);
        $summary = [...$summary, ...[
            'month' => $month['value'],
            'jalali_year' => $month['year'],
            'jalali_month' => $month['month'],
            'allocated' => $durations->format($summary['allocated_minutes']),
            'used' => $durations->format($summary['used_minutes']),
            'remaining' => $durations->format($summary['remaining_minutes']),
            'overage' => $durations->format($summary['overage_minutes']),
        ]];
        $currentCycle = $project->cycles()->containingDate(CarbonImmutable::today())->oldest('starts_at')->first();
        $currentCycleSummary = null;
        if ($currentCycle) {
            $usage = $cycleUsage->summary($currentCycle);
            $currentCycleSummary = [
                ...$usage,
                'starts_at' => PersianDate::date($currentCycle->starts_at),
                'ends_at' => PersianDate::date($currentCycle->ends_at),
                'allocated' => $durations->format($usage['allocated_minutes']),
                'used' => $durations->format($usage['consumed_minutes']),
                'remaining' => $durations->format($usage['remaining_minutes']),
                'overage' => $durations->format($usage['overage_minutes']),
                'percentage' => $usage['allocated_minutes'] > 0
                    ? min(100, (int) round(($usage['used_minutes'] / $usage['allocated_minutes']) * 100))
                    : 0,
            ];
        }

        return view('client.projects.show', [
            'project' => $presenter->present($project),
            'activities' => $activities,
            'summary' => $summary,
            'currentCycleSummary' => $currentCycleSummary,
            'serviceRoutes' => $this->serviceRoutes($request),
        ]);
    }

    /** @return array{projects: string, project: string} */
    private function serviceRoutes(Request $request): array
    {
        return $request->routeIs('account.*')
            ? ['projects' => 'account.projects.index', 'project' => 'account.projects.show']
            : ['projects' => 'client.projects.index', 'project' => 'client.projects.show'];
    }
}
