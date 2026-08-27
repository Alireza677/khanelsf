<?php

namespace App\Services;

use App\Enums\ClientProjectCycleStatus;
use App\Models\ClientProject;
use App\Models\ClientProjectCycle;
use Carbon\CarbonImmutable;
use DomainException;
use Morilog\Jalali\Jalalian;

final class ClientProjectCycleResolver
{
    public function __construct(private ClientProjectCycleUsage $usage) {}

    public function resolve(ClientProject $project, CarbonImmutable $activityDate, int $durationMinutes = 0): ?ClientProjectCycle
    {
        if ($project->monthly_hour_limit_minutes === null || $project->monthly_hour_limit_minutes <= 0) {
            return null;
        }
        $cycle = $project->cycles()->whereIn('status', [ClientProjectCycleStatus::Active, ClientProjectCycleStatus::Overdue])->oldest('starts_at')->first();
        $cycle ??= $this->createNext($project, $activityDate);
        $remaining = $this->usage->summary($cycle)['remaining_minutes'];
        if ($durationMinutes > $remaining) {
            throw new DomainException('activity_exceeds_cycle_remaining_minutes');
        }

        return $cycle;
    }

    public function createNext(ClientProject $project, CarbonImmutable $reference): ClientProjectCycle
    {
        $last = $project->cycles()->latest('starts_at')->first();
        $start = $last?->ends_at?->toImmutable() ?? $project->start_date?->toImmutable() ?? $reference->startOfDay();
        while ($last === null && $this->jalaliNext($start)->lte($reference)) {
            $start = $this->jalaliNext($start);
        }
        $end = $this->jalaliNext($start);

        return $project->cycles()->firstOrCreate(['starts_at' => $start->toDateString()], ['ends_at' => $end->toDateString(), 'allocated_minutes' => $project->monthly_hour_limit_minutes, 'status' => $end->isPast() ? ClientProjectCycleStatus::Overdue : ClientProjectCycleStatus::Active]);
    }

    private function jalaliNext(CarbonImmutable $date): CarbonImmutable
    {
        return CarbonImmutable::instance(Jalalian::fromDateTime($date)->addMonths(1)->toCarbon())->startOfDay();
    }
}
