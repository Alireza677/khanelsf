<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectCycle;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class ClientProjectCycleDelivery
{
    public function attributesForPeriod(ClientProject $project, array $period): array
    {
        if ($project->delivery_mode === 'time') {
            return [];
        }
        $start = $period['starts_at']->toDateString();
        $end = $period['ends_at']->toDateString();
        $plans = $project->planVersions()->orderBy('effective_from')->orderBy('version')->get();
        // Same-day amendments from Phase 1 have one deterministic winner.
        $plan = $plans->filter(fn ($plan) => $plan->effective_from->toDateString() <= $start)->last();
        $inside = $plans->filter(fn ($plan) => $plan->effective_from->toDateString() > $start && $plan->effective_from->toDateString() < $end);
        // The initial contract may begin within its first anchored month.
        if (! $plan && $inside->isNotEmpty() && $inside->pluck('effective_from')->unique()->count() === 1 && $inside->first()->id === $plans->first()?->id) {
            $plan = $inside->last();
            $inside = collect();
        }
        if (! $plan || $inside->isNotEmpty() || $plan->agreement_snapshot['delivery_mode'] !== $project->delivery_mode || $plan->agreement_snapshot['schedule_mode'] !== $project->schedule_mode) {
            throw ValidationException::withMessages(['effective_from' => 'قرارداد مؤثر مشخص نیست یا اصلاحیه با مرز دوره تداخل دارد.']);
        }

        return ['client_project_plan_version_id' => $plan->id, 'agreement_snapshot' => $plan->agreement_snapshot, 'delivery_mode' => $project->delivery_mode, 'delivery_status' => 'planned'];
    }

    public function snapshotCommitments(ClientProjectCycle $cycle): void
    {
        if ($cycle->delivery_mode === 'time') {
            return;
        }
        foreach ($cycle->agreement_snapshot['deliverables'] as $index => $line) {
            $cycle->commitments()->firstOrCreate(['definition_index' => $index], [
                ...array_intersect_key($line, array_flip(['deliverable_type_id', 'name', 'description', 'quantity', 'unit', 'approval_required', 'revision_allowance', 'scheduling_rules'])),
                'client_project_plan_version_id' => $cycle->client_project_plan_version_id,
                'definition_snapshot' => $line,
            ]);
        }
    }

    public function assertAmendmentDate(ClientProject $project, string $date): void
    {
        if ($project->delivery_mode === 'time' || ! $project->planVersions()->exists()) {
            return;
        }
        $period = app(ClientProjectCycleResolver::class)->periodForDate($project, CarbonImmutable::parse($date));
        if ($date <= today()->toDateString()
            || $project->planVersions()->whereDate('effective_from', '>=', $date)->exists()
            || ! $period || $period['starts_at']->toDateString() !== $date
            || $project->cycles()->whereDate('ends_at', '>', $date)->whereNotNull('agreement_snapshot')->exists()) {
            throw ValidationException::withMessages(['effective_from' => 'اصلاحیه تحویل باید در شروع یک دوره آینده و بدون تعهد ثبت‌شده مؤثر شود. در پروژه مدت‌دار، قرارداد همان دوره ثابت می‌ماند.']);
        }
    }
}
