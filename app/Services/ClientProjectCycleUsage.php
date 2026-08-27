<?php

namespace App\Services;

use App\Models\ClientProjectActivity;
use App\Models\ClientProjectCycle;

final class ClientProjectCycleUsage
{
    public function consumed(ClientProjectCycle $cycle): int
    {
        return (int) $cycle->activities()->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED)->sum('duration_minutes');
    }

    public function summary(ClientProjectCycle $cycle): array
    {
        $consumed = $this->consumed($cycle);

        return ['allocated_minutes' => $cycle->allocated_minutes, 'consumed_minutes' => $consumed, 'remaining_minutes' => max(0, $cycle->allocated_minutes - $consumed)];
    }
}
