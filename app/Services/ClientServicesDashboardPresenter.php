<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Support\PersianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ClientServicesDashboardPresenter
{
    public function __construct(
        private readonly ClientProjectCycleUsage $cycleUsage,
        private readonly ClientProjectPresenter $projects,
        private readonly ClientProjectSchedulePresenter $schedules,
        private readonly DurationFormatter $durations,
    ) {}

    public function present(Collection $projects, CarbonImmutable $month): array
    {
        $date = $month->startOfDay();
        $projectCards = $projects->map(function (ClientProject $project) use ($date): array {
            $cycle = $project->relationLoaded('currentCycle')
                ? $project->currentCycle
                : $project->cycles->sortBy('starts_at')
                    ->first(fn ($cycle): bool => $cycle->containsDate($date));
            $summary = $cycle ? $this->cycleUsage->summary($cycle) : null;
            $timeline = $this->schedules->timeline($project, $cycle, $date);

            return [
                ...$this->projects->present($project),
                'used_minutes' => $summary['consumed_minutes'] ?? 0,
                'used_time' => $this->durations->format($summary['consumed_minutes'] ?? 0),
                'limit_minutes' => $summary['allocated_minutes'] ?? null,
                'limit_time' => $summary ? $this->durations->format($summary['allocated_minutes']) : null,
                'remaining_time' => $summary ? $this->durations->format($summary['remaining_minutes']) : null,
                'overage_minutes' => $summary['overage_minutes'] ?? 0,
                'overage_time' => ($summary['overage_minutes'] ?? 0) > 0
                    ? $this->durations->format($summary['overage_minutes'])
                    : null,
                'cycle_start' => $cycle ? PersianDate::date($cycle->starts_at) : null,
                'cycle_end' => $cycle ? PersianDate::date($cycle->ends_at) : null,
                'payment' => $this->payment($project),
                'timeline' => $timeline,
            ];
        });

        $used = (int) $projectCards->sum('used_minutes');
        $hasLimit = $projectCards->isNotEmpty() && $projectCards->every(fn (array $project): bool => $project['limit_minutes'] !== null);
        $limit = $hasLimit ? (int) $projectCards->sum('limit_minutes') : null;
        $remaining = $limit === null ? null : max(0, $limit - $used);
        $overage = $limit === null ? 0 : max(0, $used - $limit);
        $percentage = $limit === null ? null : ($limit === 0 ? ($used > 0 ? 100 : 0) : min(100, (int) round(($used / $limit) * 100)));

        return [
            'projects' => $projectCards,
            'current_cycles' => [
                'used_minutes' => $used,
                'used_time' => $this->durations->format($used),
                'limit_minutes' => $limit,
                'limit_time' => $limit === null ? null : $this->durations->format($limit),
                'remaining_time' => $remaining === null ? null : $this->durations->format($remaining),
                'overage_time' => $overage > 0 ? $this->durations->format($overage) : null,
                'percentage' => $percentage,
                'chart_percentage' => $percentage ?? 0,
                'has_limit' => $limit !== null,
            ],
        ];
    }

    /** @return array{label: string, state: string} */
    private function payment(ClientProject $project): array
    {
        $invoice = $project->cycles
            ->sortByDesc(fn ($cycle) => $cycle->ends_at?->getTimestamp() ?? 0)
            ->first(fn ($cycle) => $cycle->invoice !== null)?->invoice;

        if ($invoice === null) {
            return ['label' => 'بدون صورتحساب', 'state' => 'neutral'];
        }

        return [
            'label' => $invoice->status->label(),
            'state' => match ($invoice->status->value) {
                'paid' => 'paid',
                'issued' => 'pending',
                'cancelled' => 'overdue',
                default => 'neutral',
            },
        ];
    }
}
