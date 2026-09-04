<?php

namespace App\Services;

use App\Models\ClientProjectActivity;

final class ClientProjectActivityOverage
{
    public function isOverage(ClientProjectActivity $activity): bool
    {
        if (! $activity->exists
            || ! $activity->client_project_cycle_id
            || $activity->status === ClientProjectActivity::STATUS_CANCELLED) {
            return false;
        }

        $cycle = $activity->cycle;
        if (! $cycle) {
            return false;
        }

        $activityDate = $activity->activity_date->toDateString();
        $usedThroughActivity = (int) $cycle->activities()
            ->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED)
            ->where(function ($query) use ($activity, $activityDate): void {
                $query->whereDate('activity_date', '<', $activityDate)
                    ->orWhere(function ($query) use ($activity, $activityDate): void {
                        $query->whereDate('activity_date', $activityDate)
                            ->where('id', '<=', $activity->getKey());
                    });
            })
            ->sum('duration_minutes');

        return $usedThroughActivity > (int) $cycle->allocated_minutes;
    }
}
