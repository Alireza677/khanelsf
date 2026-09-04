<?php

namespace App\Services;

use App\Models\ClientProjectActivity;
use App\Models\ClientProjectCycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ClientProjectCycleUsage
{
    public function consumed(ClientProjectCycle $cycle, ?int $excludeActivityId = null): int
    {
        if ($excludeActivityId === null && array_key_exists('consumed_minutes', $cycle->getAttributes())) {
            return (int) $cycle->getAttribute('consumed_minutes');
        }

        return (int) $cycle->activities()
            ->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED)
            ->when($excludeActivityId, fn ($query) => $query->where('id', '!=', $excludeActivityId))
            ->sum('duration_minutes');
    }

    public function withConsumedAggregate(Builder|Relation $query): Builder|Relation
    {
        return $query->withSum([
            'activities as consumed_minutes' => fn (Builder $query): Builder => $query
                ->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED),
        ], 'duration_minutes');
    }

    public function summary(ClientProjectCycle $cycle, ?int $excludeActivityId = null): array
    {
        $consumed = $this->consumed($cycle, $excludeActivityId);

        return ['allocated_minutes' => $cycle->allocated_minutes, 'consumed_minutes' => $consumed, 'remaining_minutes' => max(0, $cycle->allocated_minutes - $consumed)];
    }
}
