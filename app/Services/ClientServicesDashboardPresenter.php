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
        private readonly DurationFormatter $durations,
    ) {}

    public function present(Collection $projects, CarbonImmutable $month): array
    {
        $date = $month->startOfDay();
        $projectCards = $projects->map(function (ClientProject $project) use ($date): array {
            $cycle = $project->cycles->sortBy('starts_at')
                ->first(fn ($cycle): bool => $cycle->containsDate($date));
            $summary = $cycle ? $this->cycleUsage->summary($cycle) : null;

            return [
                ...$this->projects->present($project),
                'used_minutes' => $summary['consumed_minutes'] ?? 0,
                'used_time' => $this->durations->format($summary['consumed_minutes'] ?? 0),
                'limit_minutes' => $summary['allocated_minutes'] ?? null,
                'limit_time' => $summary ? $this->durations->format($summary['allocated_minutes']) : null,
                'remaining_time' => $summary ? $this->durations->format($summary['remaining_minutes']) : null,
                'cycle_start' => $cycle ? PersianDate::date($cycle->starts_at) : null,
                'cycle_end' => $cycle ? PersianDate::date($cycle->ends_at) : null,
                'payment' => $this->payment($project),
                'timeline' => $this->timeline($project),
            ];
        });

        $used = (int) $projectCards->sum('used_minutes');
        $hasLimit = $projectCards->isNotEmpty() && $projectCards->every(fn (array $project): bool => $project['limit_minutes'] !== null);
        $limit = $hasLimit ? (int) $projectCards->sum('limit_minutes') : null;
        $remaining = $limit === null ? null : max(0, $limit - $used);
        $overage = $limit === null ? 0 : max(0, $used - $limit);
        $percentage = $limit === null ? null : ($limit === 0 ? ($used > 0 ? 100 : 0) : (int) round(($used / $limit) * 100));

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
                'chart_percentage' => min(100, $percentage ?? 0),
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

    /** @return array{state: string, label: string, percentage: int, today_percentage: int|null, detail: string} */
    private function timeline(ClientProject $project): array
    {
        $today = CarbonImmutable::today();
        $start = $project->start_date ? CarbonImmutable::instance($project->start_date)->startOfDay() : null;
        $end = $project->end_date ? CarbonImmutable::instance($project->end_date)->startOfDay() : null;

        $state = match (true) {
            $project->status === ClientProject::STATUS_COMPLETED => 'completed',
            $start && $today->lt($start) => 'upcoming',
            $end && $today->gt($end) => 'overdue',
            default => 'active',
        };

        $label = match ($state) {
            'completed' => 'تکمیل‌شده',
            'upcoming' => 'هنوز شروع نشده',
            'overdue' => 'از موعد گذشته',
            default => 'در حال اجرا',
        };

        $percentage = max(0, min(100, $project->progress));
        $todayPercentage = null;
        if ($start && $end && $end->gt($start)) {
            $totalDays = max(1, $start->diffInDays($end));
            $todayPercentage = (int) round(max(0, min($totalDays, $start->diffInDays($today, false))) / $totalDays * 100);
            $percentage = $state === 'completed' ? 100 : $todayPercentage;
        }

        $detail = match ($state) {
            'completed' => 'پروژه تکمیل شده است',
            'upcoming' => PersianDate::digits((int) $today->diffInDays($start)).' روز تا شروع پروژه',
            'overdue' => PersianDate::digits((int) $end->diffInDays($today)).' روز از موعد تحویل گذشته',
            default => $end
                ? PersianDate::digits((int) $today->diffInDays($end)).' روز تا تحویل باقی‌مانده'
                : 'تاریخ تحویل تعیین نشده است',
        };

        return [
            'state' => $state,
            'label' => $label,
            'percentage' => $percentage,
            'today_percentage' => $todayPercentage,
            'detail' => $detail,
        ];
    }
}
