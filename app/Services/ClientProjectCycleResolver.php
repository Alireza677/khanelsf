<?php

namespace App\Services;

use App\Enums\ClientProjectCycleStatus;
use App\Models\ClientProject;
use App\Models\ClientProjectCycle;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

final class ClientProjectCycleResolver
{
    public function __construct(private ClientProjectCycleUsage $usage) {}

    public function resolve(ClientProject $project, CarbonImmutable $activityDate, int $durationMinutes = 0): ?ClientProjectCycle
    {
        return $this->resolveForDate($project, $activityDate, $durationMinutes);
    }

    public function resolveForDate(ClientProject $project, CarbonInterface $activityDate, int $durationMinutes = 0, ?int $excludeActivityId = null): ?ClientProjectCycle
    {
        if ($project->monthly_hour_limit_minutes === null || $project->monthly_hour_limit_minutes <= 0) {
            return null;
        }

        $activityDate = CarbonImmutable::instance($activityDate)->startOfDay();
        if ($project->start_date && $activityDate->lt($project->start_date->startOfDay())) {
            return null;
        }

        $cycle = $this->mutableCycles($project)->containingDate($activityDate)->oldest('starts_at')->first();
        if (! $cycle && $project->cycles()->containingDate($activityDate)->exists()) {
            throw new DomainException('activity_date_in_protected_cycle');
        }

        $cycle ??= $this->createThroughDate($project, $activityDate);
        $remaining = $this->usage->summary($cycle, $excludeActivityId)['remaining_minutes'];
        if ($durationMinutes > $remaining) {
            throw new DomainException('activity_exceeds_cycle_remaining_minutes');
        }

        return $cycle;
    }

    public function createNext(ClientProject $project, CarbonImmutable $reference): ClientProjectCycle
    {
        return $this->createThroughDate($project, $reference);
    }

    public function findForDate(ClientProject $project, CarbonInterface $date): ?ClientProjectCycle
    {
        return $project->cycles()->containingDate($date)->oldest('starts_at')->first();
    }

    /** @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null */
    public function periodForDate(ClientProject $project, CarbonInterface $date, CarbonInterface|string|null $anchorOverride = null): ?array
    {
        $date = CarbonImmutable::instance($date)->startOfDay();
        $anchor = $anchorOverride === null
            ? $this->anchorFor($project, $date)
            : CarbonImmutable::parse($anchorOverride)->startOfDay();

        if ($date->lt($anchor)) {
            return null;
        }

        $start = $anchor;
        $end = $this->jalaliNext($start);
        while ($date->gte($end)) {
            $start = $end;
            $end = $this->jalaliNext($start);
        }

        return ['starts_at' => $start, 'ends_at' => $end];
    }

    private function createThroughDate(ClientProject $project, CarbonImmutable $reference): ClientProjectCycle
    {
        $period = $this->periodForDate($project, $reference);
        if ($period === null) {
            throw new DomainException('activity_date_before_project_start');
        }

        $start = $this->anchorFor($project, $reference);
        do {
            $end = $this->jalaliNext($start);
            $cycle = $project->cycles()->whereDate('starts_at', $start->toDateString())->first();
            if ($cycle && $cycle->ends_at->toDateString() !== $end->toDateString()) {
                throw new DomainException('cycle_schedule_conflict');
            }
            $cycle ??= $project->cycles()->create([
                'starts_at' => $start->toDateString(),
                'ends_at' => $end->toDateString(),
                'allocated_minutes' => $project->monthly_hour_limit_minutes,
                'status' => $end->isPast() ? ClientProjectCycleStatus::Overdue : ClientProjectCycleStatus::Active,
            ]);
            $start = $end;
        } while (! $cycle->containsDate($reference));

        if ($cycle->status === ClientProjectCycleStatus::Invoiced
            || $this->isClaimed($cycle)) {
            throw new DomainException('activity_date_in_protected_cycle');
        }

        return $cycle;
    }

    private function mutableCycles(ClientProject $project)
    {
        return $project->cycles()
            ->where('status', '!=', ClientProjectCycleStatus::Invoiced)
            ->whereDoesntHave('invoice')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('invoice_cycle_claims')
                ->whereColumn('invoice_cycle_claims.client_project_cycle_id', 'client_project_cycles.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('client_project_activities as claimed_cycle_activities')
                ->join('invoice_activity_claims', 'invoice_activity_claims.client_project_activity_id', '=', 'claimed_cycle_activities.id')
                ->whereColumn('claimed_cycle_activities.client_project_cycle_id', 'client_project_cycles.id'));
    }

    private function isClaimed(ClientProjectCycle $cycle): bool
    {
        return $cycle->status === ClientProjectCycleStatus::Invoiced
            || $cycle->invoice()->exists()
            || DB::table('invoice_cycle_claims')->where('client_project_cycle_id', $cycle->id)->exists()
            || $cycle->activities()->whereExists(fn ($query) => $query->selectRaw('1')
                ->from('invoice_activity_claims')
                ->whereColumn('invoice_activity_claims.client_project_activity_id', 'client_project_activities.id'))->exists();
    }

    private function anchorFor(ClientProject $project, CarbonImmutable $fallback): CarbonImmutable
    {
        if ($project->start_date) {
            return $project->start_date->toImmutable()->startOfDay();
        }

        $earliest = $project->relationLoaded('cycles')
            ? $project->cycles->min('starts_at')
            : $project->cycles()->oldest('starts_at')->value('starts_at');

        return $earliest ? CarbonImmutable::parse($earliest)->startOfDay() : $fallback->startOfDay();
    }

    private function jalaliNext(CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::instance(Jalalian::fromDateTime($date)->addMonths(1)->toCarbon())->startOfDay();
    }
}
