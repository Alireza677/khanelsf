<?php

namespace App\Services;

use App\Enums\ClientProjectCycleStatus;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\ClientProjectCycle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClientProjectCycleReconciler
{
    public function __construct(
        private ClientProjectCycleResolver $resolver,
        private ClientProjectCycleUsage $usage,
        private RecalculateClientProjectCycle $recalculate,
    ) {}

    public function updateProject(ClientProject $project, array $attributes): ClientProject
    {
        return DB::transaction(function () use ($project, $attributes): ClientProject {
            $project = ClientProject::query()->whereKey($project)->lockForUpdate()->firstOrFail();
            $proposed = clone $project;
            $proposed->forceFill($attributes);
            $startChanged = $this->dateValue($project->start_date) !== $this->dateValue($proposed->start_date);
            $allocationChanged = $project->monthly_hour_limit_minutes !== $proposed->monthly_hour_limit_minutes;

            if ($startChanged) {
                $this->assertProtectedHistoryCompatible($project, $proposed);
            }

            $project->fill($attributes)->save();
            $project = $project->fresh();

            if ($startChanged) {
                $this->reconcileLocked($project, true);
            }

            if ($allocationChanged) {
                $this->synchronizeMutableCycleAllocations($project->fresh());
            }

            return $project->fresh();
        }, 3);
    }

    /** @return array<string, mixed> */
    public function plan(ClientProject $project): array
    {
        $project->loadMissing(['activities.cycle', 'cycles']);
        $protectedCycleIds = $this->protectedCycleIds($project);
        $claimedActivityIds = DB::table('invoice_activity_claims')
            ->whereIn('client_project_activity_id', $project->activities->pluck('id'))
            ->pluck('client_project_activity_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();
        $cyclesByPeriod = $project->cycles->keyBy(fn (ClientProjectCycle $cycle): string => $this->periodKey($cycle->starts_at, $cycle->ends_at));

        $rows = $project->activities->sortBy([['activity_date', 'asc'], ['id', 'asc']])->map(function (ClientProjectActivity $activity) use ($project, $protectedCycleIds, $claimedActivityIds, $cyclesByPeriod): array {
            $period = $project->monthly_hour_limit_minutes
                ? $this->resolver->periodForDate($project, $activity->activity_date)
                : null;
            $proposedCycle = $period ? $cyclesByPeriod->get($this->periodKey($period['starts_at'], $period['ends_at'])) : null;
            $oldCycle = $activity->cycle;
            $samePeriod = $oldCycle && $period
                && $this->periodKey($oldCycle->starts_at, $oldCycle->ends_at) === $this->periodKey($period['starts_at'], $period['ends_at']);
            $changeRequired = ! $samePeriod || ($period === null && $oldCycle !== null);
            $claimed = $claimedActivityIds->has($activity->id);
            $oldProtected = $oldCycle && $protectedCycleIds->has($oldCycle->id);
            $protectedContainingCycle = $project->cycles->first(fn (ClientProjectCycle $cycle): bool => $protectedCycleIds->has($cycle->id)
                && $cycle->containsDate($activity->activity_date));
            $targetProtected = ($proposedCycle && $protectedCycleIds->has($proposedCycle->id))
                || ($protectedContainingCycle && (int) $oldCycle?->id !== (int) $protectedContainingCycle->id);
            $allowed = ! $changeRequired || (! $claimed && ! $oldProtected && ! $targetProtected);
            $reason = match (true) {
                ! $changeRequired => 'بدون تغییر',
                $claimed => 'فعالیت در فاکتور ثبت شده است',
                $oldProtected => 'دوره فعلی محافظت‌شده است',
                $targetProtected => 'دوره مقصد محافظت‌شده است',
                $period === null => 'تاریخ فعالیت پیش از شروع قرارداد یا پروژه بدون سهم دوره‌ای است',
                default => 'قابل اعمال',
            };

            return [
                'activity_id' => $activity->id,
                'activity_date' => $activity->activity_date->toDateString(),
                'old_cycle_id' => $oldCycle?->id,
                'old_cycle' => $oldCycle ? $this->periodLabel($oldCycle->starts_at, $oldCycle->ends_at) : null,
                'proposed_cycle_id' => $proposedCycle?->id,
                'proposed_cycle' => $period ? $this->periodLabel($period['starts_at'], $period['ends_at']) : null,
                'claimed' => $claimed,
                'protected' => (bool) ($claimed || $oldProtected || $targetProtected),
                'change_required' => $changeRequired,
                'allowed' => $allowed,
                'reason' => $reason,
                'duration_minutes' => $activity->duration_minutes,
                'status' => $activity->status,
            ];
        })->values();

        $today = CarbonImmutable::today();
        $currentCycle = $project->cycles->sortBy('starts_at')->first(fn (ClientProjectCycle $cycle): bool => $cycle->containsDate($today));
        $currentPeriod = $project->monthly_hour_limit_minutes ? $this->resolver->periodForDate($project, $today) : null;
        $projectedUsage = $currentPeriod ? $rows->sum(function (array $row) use ($currentPeriod, $project): int {
            if ($row['status'] === ClientProjectActivity::STATUS_CANCELLED) {
                return 0;
            }
            $oldCycle = $project->cycles->firstWhere('id', $row['old_cycle_id']);
            $resultingPeriod = $row['allowed']
                ? $row['proposed_cycle']
                : ($oldCycle ? $this->periodLabel($oldCycle->starts_at, $oldCycle->ends_at) : null);

            return $resultingPeriod === $this->periodLabel($currentPeriod['starts_at'], $currentPeriod['ends_at'])
                ? (int) $row['duration_minutes']
                : 0;
        }) : 0;

        return [
            'project' => [
                'id' => $project->id,
                'title' => $project->title,
                'start_date' => $this->dateValue($project->start_date),
                'end_date' => $this->dateValue($project->end_date),
            ],
            'activities' => $rows->all(),
            'current_cycle_before' => $currentCycle ? [
                'id' => $currentCycle->id,
                'period' => $this->periodLabel($currentCycle->starts_at, $currentCycle->ends_at),
                'consumed_minutes' => $this->usage->consumed($currentCycle),
            ] : null,
            'current_cycle_after' => $currentPeriod ? [
                'period' => $this->periodLabel($currentPeriod['starts_at'], $currentPeriod['ends_at']),
                'consumed_minutes' => (int) $projectedUsage,
            ] : null,
        ];
    }

    /** @return array<string, mixed> */
    public function reconcile(ClientProject $project, bool $strictProtected = false): array
    {
        return DB::transaction(function () use ($project, $strictProtected): array {
            $project = ClientProject::query()->whereKey($project)->lockForUpdate()->firstOrFail();
            $this->reconcileLocked($project, $strictProtected);

            return $this->plan($project->fresh());
        }, 3);
    }

    private function reconcileLocked(ClientProject $project, bool $strictProtected): void
    {
        $project->load(['activities.cycle', 'cycles']);
        $protectedCycleIds = $this->protectedCycleIds($project);
        $claimedActivityIds = DB::table('invoice_activity_claims')
            ->whereIn('client_project_activity_id', $project->activities->pluck('id'))
            ->pluck('client_project_activity_id')
            ->map(fn ($id): int => (int) $id)
            ->flip();

        foreach ($project->cycles as $cycle) {
            if ($protectedCycleIds->has($cycle->id) || $this->cycleMatchesSchedule($project, $cycle)) {
                continue;
            }

            $cycle->delete();
        }

        $affectedCycleIds = collect();
        $mutableActivities = collect();
        foreach ($project->activities()->with('cycle')->orderBy('activity_date')->orderBy('id')->get() as $activity) {
            $oldCycle = $activity->cycle;
            $oldProtected = $oldCycle && $protectedCycleIds->has($oldCycle->id);
            if ($claimedActivityIds->has($activity->id) || $oldProtected) {
                if ($strictProtected && ! $this->activityMatchesSchedule($project, $activity)) {
                    $this->throwProtectedHistoryError();
                }

                continue;
            }

            $period = $project->monthly_hour_limit_minutes
                ? $this->resolver->periodForDate($project, $activity->activity_date)
                : null;
            $protectedTarget = $project->cycles()
                ->containingDate($activity->activity_date)
                ->whereIn('id', $protectedCycleIds->keys()->all())
                ->first();
            if ($protectedTarget && (int) $activity->client_project_cycle_id !== (int) $protectedTarget->id) {
                if ($strictProtected) {
                    $this->throwProtectedHistoryError();
                }

                continue;
            }

            $affectedCycleIds->push($activity->client_project_cycle_id);
            $activity->forceFill(['client_project_cycle_id' => null])->saveQuietly();
            $mutableActivities->push($activity->fresh());
        }

        foreach ($mutableActivities as $activity) {
            try {
                $duration = $activity->status === ClientProjectActivity::STATUS_CANCELLED ? 0 : $activity->duration_minutes;
                $newCycle = $this->resolver->resolveForDate($project, $activity->activity_date, $duration, $activity->id);
            } catch (\DomainException $exception) {
                if ($exception->getMessage() === 'activity_date_in_protected_cycle' && ! $strictProtected) {
                    continue;
                }

                throw ValidationException::withMessages([
                    'start_date' => $exception->getMessage() === 'activity_exceeds_cycle_remaining_minutes'
                        ? 'جابجایی دوره‌ها باعث می‌شود زمان ثبت‌شده از سهم دوره بیشتر شود.'
                        : 'دوره‌های پروژه با تاریخ جدید قابل بازسازی نیستند.',
                ]);
            }

            $newCycleId = $newCycle?->id;
            if ((int) $activity->client_project_cycle_id === (int) $newCycleId) {
                continue;
            }

            $affectedCycleIds->push($newCycleId);
            $activity->forceFill(['client_project_cycle_id' => $newCycleId])->saveQuietly();
        }

        if ($project->monthly_hour_limit_minutes && (! $project->start_date || CarbonImmutable::today()->gte($project->start_date))) {
            try {
                $this->resolver->resolveForDate($project, CarbonImmutable::today());
            } catch (\DomainException $exception) {
                if ($strictProtected || $exception->getMessage() !== 'activity_date_in_protected_cycle') {
                    throw $exception;
                }
            }
        }

        ClientProjectCycle::query()->whereIn('id', $affectedCycleIds->filter()->unique()->all())->get()
            ->each(fn (ClientProjectCycle $cycle) => $this->recalculate->handle($cycle));
    }

    private function assertProtectedHistoryCompatible(ClientProject $current, ClientProject $proposed): void
    {
        $current->load(['activities.cycle', 'cycles']);
        $protectedCycleIds = $this->protectedCycleIds($current);

        foreach ($current->cycles as $cycle) {
            if ($protectedCycleIds->has($cycle->id) && ! $this->cycleMatchesSchedule($proposed, $cycle)) {
                $this->throwProtectedHistoryError();
            }
        }

        $claimedIds = DB::table('invoice_activity_claims')
            ->whereIn('client_project_activity_id', $current->activities->pluck('id'))
            ->pluck('client_project_activity_id');
        foreach ($current->activities->whereIn('id', $claimedIds) as $activity) {
            if (! $this->activityMatchesSchedule($proposed, $activity)) {
                $this->throwProtectedHistoryError();
            }
        }
    }

    private function synchronizeMutableCycleAllocations(ClientProject $project): void
    {
        $project->load(['activities', 'cycles']);
        $protectedCycleIds = $this->protectedCycleIds($project);
        $cycles = $project->cycles
            ->filter(fn (ClientProjectCycle $cycle): bool => $cycle->ends_at->isAfter(CarbonImmutable::today()))
            ->reject(fn (ClientProjectCycle $cycle): bool => $protectedCycleIds->has($cycle->id));
        $allocation = $project->monthly_hour_limit_minutes;
        $consumedMinutesByCycle = $project->activities
            ->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED)
            ->groupBy('client_project_cycle_id')
            ->map(fn (Collection $activities): int => (int) $activities->sum('duration_minutes'));

        if ($allocation !== null) {
            foreach ($cycles as $cycle) {
                if ($consumedMinutesByCycle->get($cycle->id, 0) > $allocation) {
                    throw ValidationException::withMessages([
                        'monthly_limit_hours' => 'سهم دوره نمی‌تواند کمتر از زمان مصرف‌شده در دوره جاری یا آینده باشد.',
                    ]);
                }
            }
        }

        if ($allocation === null || $allocation <= 0) {
            $cycles->each->delete();

            return;
        }

        foreach ($cycles as $cycle) {
            $cycle->update(['allocated_minutes' => $allocation]);
            $this->recalculate->handle($cycle);
        }

        if (! $project->start_date || CarbonImmutable::today()->gte($project->start_date)) {
            try {
                $this->resolver->resolveForDate($project, CarbonImmutable::today());
            } catch (\DomainException $exception) {
                if ($exception->getMessage() !== 'activity_date_in_protected_cycle') {
                    throw $exception;
                }
            }
        }
    }

    private function cycleMatchesSchedule(ClientProject $project, ClientProjectCycle $cycle): bool
    {
        $period = $this->resolver->periodForDate($project, $cycle->starts_at);

        return $period !== null
            && $this->periodKey($period['starts_at'], $period['ends_at']) === $this->periodKey($cycle->starts_at, $cycle->ends_at);
    }

    private function activityMatchesSchedule(ClientProject $project, ClientProjectActivity $activity): bool
    {
        $period = $this->resolver->periodForDate($project, $activity->activity_date);

        return $activity->cycle !== null && $period !== null
            && $this->periodKey($period['starts_at'], $period['ends_at']) === $this->periodKey($activity->cycle->starts_at, $activity->cycle->ends_at);
    }

    /** @return Collection<int, true> */
    private function protectedCycleIds(ClientProject $project): Collection
    {
        $ids = $project->cycles
            ->filter(fn (ClientProjectCycle $cycle): bool => $cycle->status === ClientProjectCycleStatus::Invoiced)
            ->pluck('id');
        $ids = $ids->merge(DB::table('invoice_cycle_claims')->whereIn('client_project_cycle_id', $project->cycles->pluck('id'))->pluck('client_project_cycle_id'));
        $ids = $ids->merge(DB::table('invoices')->whereIn('client_project_cycle_id', $project->cycles->pluck('id'))->where('status', '!=', 'cancelled')->pluck('client_project_cycle_id'));
        $claimedActivityIds = DB::table('invoice_activity_claims')
            ->whereIn('client_project_activity_id', $project->activities->pluck('id'))
            ->pluck('client_project_activity_id');
        $ids = $ids->merge($project->activities->whereIn('id', $claimedActivityIds)->pluck('client_project_cycle_id')->filter());

        return $ids->map(fn ($id): int => (int) $id)->unique()->flip();
    }

    private function throwProtectedHistoryError(): never
    {
        throw ValidationException::withMessages([
            'start_date' => 'تغییر تاریخ شروع با دوره یا فعالیت فاکتورشده تداخل دارد. ابتدا فاکتور پیش‌نویس را لغو کنید یا تاریخ مؤثر دیگری انتخاب کنید.',
        ]);
    }

    private function periodKey(mixed $start, mixed $end): string
    {
        return CarbonImmutable::parse($start)->toDateString().'|'.CarbonImmutable::parse($end)->toDateString();
    }

    private function periodLabel(mixed $start, mixed $end): string
    {
        return CarbonImmutable::parse($start)->toDateString().' → '.CarbonImmutable::parse($end)->toDateString();
    }

    private function dateValue(mixed $date): ?string
    {
        return $date ? CarbonImmutable::parse($date)->toDateString() : null;
    }
}
