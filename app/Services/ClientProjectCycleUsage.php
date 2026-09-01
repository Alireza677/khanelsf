<?php

namespace App\Services;

use App\Models\ClientProjectActivity;
use App\Models\ClientProjectCycle;

final class ClientProjectCycleUsage
{
    public function consumed(ClientProjectCycle $cycle, ?int $excludeActivityId = null): int
    {
        return (int) $cycle->activities()
            ->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED)
            ->when($excludeActivityId, fn ($query) => $query->where('id', '!=', $excludeActivityId))
            ->sum('duration_minutes');
    }

    public function summary(ClientProjectCycle $cycle, ?int $excludeActivityId = null): array
    {
        $consumed = $this->consumed($cycle, $excludeActivityId);

        return ['allocated_minutes' => $cycle->allocated_minutes, 'consumed_minutes' => $consumed, 'remaining_minutes' => max(0, $cycle->allocated_minutes - $consumed)];
    }
}
