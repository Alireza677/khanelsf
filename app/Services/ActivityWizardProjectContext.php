<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\Customer;
use Carbon\CarbonImmutable;

class ActivityWizardProjectContext
{
    public function __construct(
        private readonly ClientProjectMonthlyTimeService $time,
        private readonly DurationFormatter $durations,
    ) {}

    public function options(): array
    {
        return $this->query()->get()->mapWithKeys(fn (ClientProject $project): array => [
            $project->id => $project->title.' — '.$project->customer->display_name,
        ])->all();
    }

    public function recentOptions(): array
    {
        return $this->query()->limit(5)->get()->mapWithKeys(fn (ClientProject $project): array => [
            $project->id => $project->title.' — '.$project->customer->display_name,
        ])->all();
    }

    public function find(int|string|null $projectId): ?ClientProject
    {
        if (! $projectId) {
            return null;
        }

        return $this->query()->find((int) $projectId);
    }

    public function summary(int|string|null $projectId): ?array
    {
        $project = $this->find($projectId);

        if (! $project) {
            return null;
        }

        $summary = $this->time->summarize($project, CarbonImmutable::now()->startOfMonth());

        return [
            'project' => $project,
            'allocated' => $project->monthly_hour_limit_minutes === null
                ? 'بدون محدودیت'
                : $this->durations->format($project->monthly_hour_limit_minutes),
            'used' => $this->durations->format($summary['used_minutes']),
            'usage_text' => $project->monthly_hour_limit_minutes === null
                ? $this->durations->format($summary['used_minutes']).' ثبت شده · بدون محدودیت ماهانه'
                : $this->durations->format($summary['used_minutes']).' از '.$this->durations->format($project->monthly_hour_limit_minutes).' مصرف شده',
        ];
    }

    private function query()
    {
        return ClientProject::query()
            ->with('customer')
            ->whereHas('customer', fn ($query) => $query->where('status', Customer::STATUS_ACTIVE))
            ->latest('updated_at');
    }
}
