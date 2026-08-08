<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\ClientProjectAccess;
use App\Services\ClientProjectActivityPresenter;
use App\Services\ClientProjectMonthlyTimeService;
use App\Services\ClientProjectPresenter;
use App\Services\DurationFormatter;
use App\Services\MonthResolver;
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

        return view('client.projects.index', compact('projects'));
    }

    public function show(
        Request $request,
        int $project,
        ClientProjectAccess $access,
        ClientProjectPresenter $presenter,
        ClientProjectActivityPresenter $activityPresenter,
        ClientProjectMonthlyTimeService $timeService,
        DurationFormatter $durations,
        MonthResolver $months,
    ): View {
        $project = $access->findFor($request->attributes->get('portalCustomer'), $project);
        Gate::forUser($request->user('client'))->authorize('view', $project);
        $month = $months->resolve($request->query('month'));
        $activities = $project->activities()->publishedForClient()->inMonth($month)
            ->latest('activity_date')->latest('id')->paginate(10)->withQueryString();
        $activities->through(fn ($activity): array => $activityPresenter->present($activity));
        $summary = $timeService->summarize($project, $month);
        $summary = [...$summary, ...[
            'allocated' => $durations->format($summary['allocated_minutes']),
            'used' => $durations->format($summary['used_minutes']),
            'remaining' => $durations->format($summary['remaining_minutes']),
            'overage' => $durations->format($summary['overage_minutes']),
        ]];

        return view('client.projects.show', [
            'project' => $presenter->present($project),
            'activities' => $activities,
            'summary' => $summary,
        ]);
    }
}
