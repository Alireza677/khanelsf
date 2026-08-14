<?php

namespace App\Services;

use App\Models\ClientProject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ClientServicesDashboardPresenter
{
    public function __construct(
        private readonly ClientProjectMonthlyTimeService $monthlyTime,
        private readonly ClientProjectPresenter $projects,
        private readonly DurationFormatter $durations,
    ) {}

    public function present(Collection $projects, CarbonImmutable $month): array
    {
        $summaries = $this->monthlyTime->summarizeMany($projects, $month);
        $projectCards = $projects->map(function (ClientProject $project) use ($summaries): array {
            $summary = $summaries->get($project->getKey());

            return [
                ...$this->projects->present($project),
                'used_minutes' => $summary['used_minutes'],
                'used_time' => $this->durations->format($summary['used_minutes']),
                'limit_time' => $summary['allocated_minutes'] === null ? null : $this->durations->format($summary['allocated_minutes']),
            ];
        });

        $used = (int) $projectCards->sum('used_minutes');
        $hasLimit = $projects->isNotEmpty() && $projects->every(fn (ClientProject $project): bool => $project->monthly_hour_limit_minutes !== null);
        $limit = $hasLimit ? (int) $projects->sum('monthly_hour_limit_minutes') : null;
        $remaining = $limit === null ? null : max(0, $limit - $used);
        $overage = $limit === null ? 0 : max(0, $used - $limit);
        $percentage = $limit === null ? null : ($limit === 0 ? ($used > 0 ? 100 : 0) : (int) round(($used / $limit) * 100));

        return [
            'projects' => $projectCards,
            'monthly' => [
                'used_minutes' => $used,
                'used_time' => $this->durations->format($used),
                'limit_minutes' => $limit,
                'limit_time' => $limit === null ? null : $this->durations->format($limit),
                'remaining_time' => $remaining === null ? null : $this->durations->format($remaining),
                'overage_time' => $overage > 0 ? $this->durations->format($overage) : null,
                'percentage' => $percentage,
                'chart_percentage' => min(100, $percentage ?? 0),
                'has_limit' => $limit !== null,
            ],
        ];
    }
}
