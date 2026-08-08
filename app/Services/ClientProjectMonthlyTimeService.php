<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use Carbon\CarbonImmutable;

class ClientProjectMonthlyTimeService
{
    public function summarize(ClientProject $project, CarbonImmutable $month): array
    {
        $base = $project->activities()->inMonth($month)->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED);
        $used = (int) (clone $base)->sum('duration_minutes');
        $allocated = $project->monthly_hour_limit_minutes;
        $remaining = $allocated === null ? null : max(0, $allocated - $used);
        $overage = $allocated === null ? 0 : max(0, $used - $allocated);
        $percentage = match (true) {
            $allocated === null => null,
            $allocated === 0 && $used === 0 => 0,
            $allocated === 0 => 100,
            default => (int) round(($used / $allocated) * 100),
        };

        return [
            'month' => $month->format('Y-m'),
            'allocated_minutes' => $allocated,
            'used_minutes' => $used,
            'remaining_minutes' => $remaining,
            'overage_minutes' => $overage,
            'usage_percentage' => $percentage,
            'is_exceeded' => $allocated !== null && $used > $allocated,
            'published_client_activity_count' => (clone $base)->publishedForClient()->count(),
            'admin_activity_count' => (clone $base)->count(),
        ];
    }
}
