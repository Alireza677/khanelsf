<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectCycle;
use App\Support\PersianDate;
use Carbon\CarbonImmutable;

final class ClientProjectSchedulePresenter
{
    /**
     * @return array{
     *     state: string,
     *     label: string,
     *     percentage: int,
     *     today_percentage: int|null,
     *     detail: string,
     *     start_label: string,
     *     end_label: string,
     *     start_date: string|null,
     *     end_date: string|null
     * }
     */
    public function timeline(
        ClientProject $project,
        ?ClientProjectCycle $currentCycle = null,
        ?CarbonImmutable $today = null,
    ): array {
        $today ??= CarbonImmutable::today();
        $dates = $this->dates($project, $currentCycle);
        $start = $dates['start'];
        $end = $dates['end'];

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
            default => $dates['source'] === 'none' ? 'دوره جاری ندارد' : 'در حال اجرا',
        };

        $percentage = $dates['source'] === 'project'
            ? max(0, min(100, (int) $project->progress))
            : 0;
        $todayPercentage = null;

        if ($state === 'completed') {
            $percentage = 100;
        }

        if ($start && $end && $end->gt($start)) {
            $totalDays = max(1, (int) $start->diffInDays($end));
            $elapsedDays = max(0, min($totalDays, (int) $start->diffInDays($today, false)));
            $todayPercentage = (int) round($elapsedDays / $totalDays * 100);
            $percentage = $state === 'completed' ? 100 : $todayPercentage;
        }

        $usesCycleTerms = $dates['source'] === 'cycle' || $project->schedule_mode === ClientProject::SCHEDULE_RECURRING;
        $detail = match ($state) {
            'completed' => 'پروژه تکمیل شده است',
            'upcoming' => PersianDate::digits((int) $today->diffInDays($start))
                .' روز تا '.($usesCycleTerms ? 'شروع دوره' : 'شروع پروژه'),
            'overdue' => PersianDate::digits((int) $end->diffInDays($today))
                .' روز از '.($usesCycleTerms ? 'پایان دوره' : 'موعد تحویل').' گذشته',
            default => $end
                ? PersianDate::digits((int) $today->diffInDays($end))
                    .' روز تا '.($usesCycleTerms ? 'پایان دوره' : 'تحویل').' باقی‌مانده'
                : ($usesCycleTerms ? 'دوره جاری تعیین نشده است' : 'تاریخ تحویل تعیین نشده است'),
        };

        return [
            'state' => $state,
            'label' => $label,
            'percentage' => $percentage,
            'today_percentage' => $todayPercentage,
            'detail' => $detail,
            'start_label' => $usesCycleTerms ? 'شروع دوره' : 'شروع پروژه',
            'end_label' => $usesCycleTerms ? 'پایان دوره' : 'تحویل پروژه',
            'start_date' => PersianDate::date($start),
            'end_date' => PersianDate::date($end),
        ];
    }

    public function deadlineLabel(
        ClientProject $project,
        ?ClientProjectCycle $currentCycle = null,
        ?CarbonImmutable $today = null,
    ): string {
        if ($project->status === ClientProject::STATUS_COMPLETED) {
            return 'تکمیل شده';
        }

        $deadline = $this->dates($project, $currentCycle)['end'];

        if (! $deadline) {
            return 'بدون ددلاین';
        }

        $today ??= CarbonImmutable::today();

        if ($deadline->isSameDay($today)) {
            return 'امروز';
        }

        if ($deadline->isAfter($today)) {
            return PersianDate::digits((int) $today->diffInDays($deadline)).' روز باقی‌مانده';
        }

        return PersianDate::digits((int) $deadline->diffInDays($today)).' روز گذشته';
    }

    /**
     * Explicit recurring projects never fall back to their legacy project dates.
     * A missing schedule_mode is the only legacy state allowed to use them.
     *
     * @return array{start: CarbonImmutable|null, end: CarbonImmutable|null, source: 'cycle'|'project'|'none'}
     */
    private function dates(ClientProject $project, ?ClientProjectCycle $currentCycle): array
    {
        if ($project->schedule_mode === ClientProject::SCHEDULE_RECURRING) {
            return $currentCycle
                ? $this->cycleDates($currentCycle)
                : ['start' => null, 'end' => null, 'source' => 'none'];
        }

        if ($project->schedule_mode === ClientProject::SCHEDULE_FIXED_PERIOD) {
            return $this->projectDates($project);
        }

        if (blank($project->schedule_mode)) {
            return $currentCycle
                ? $this->cycleDates($currentCycle)
                : $this->projectDates($project);
        }

        return ['start' => null, 'end' => null, 'source' => 'none'];
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable, source: 'cycle'} */
    private function cycleDates(ClientProjectCycle $cycle): array
    {
        return [
            'start' => CarbonImmutable::instance($cycle->starts_at)->startOfDay(),
            'end' => CarbonImmutable::instance($cycle->ends_at)->startOfDay(),
            'source' => 'cycle',
        ];
    }

    /** @return array{start: CarbonImmutable|null, end: CarbonImmutable|null, source: 'project'} */
    private function projectDates(ClientProject $project): array
    {
        return [
            'start' => $project->start_date
                ? CarbonImmutable::instance($project->start_date)->startOfDay()
                : null,
            'end' => $project->end_date
                ? CarbonImmutable::instance($project->end_date)->startOfDay()
                : null,
            'source' => 'project',
        ];
    }
}
