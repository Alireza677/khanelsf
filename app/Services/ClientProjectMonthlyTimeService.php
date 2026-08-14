<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

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

    /** @return Collection<int, array<string, mixed>> */
    public function summarizeMany(Collection $projects, CarbonImmutable $month): Collection
    {
        if ($projects->isEmpty()) {
            return collect();
        }

        $rows = ClientProjectActivity::query()
            ->whereIn('client_project_id', $projects->pluck('id')->all())
            ->inMonth($month)
            ->where('status', '!=', ClientProjectActivity::STATUS_CANCELLED)
            ->selectRaw('client_project_id, SUM(duration_minutes) as used_minutes')
            ->selectRaw('SUM(CASE WHEN status = ? AND visibility = ? THEN 1 ELSE 0 END) as published_count', [ClientProjectActivity::STATUS_PUBLISHED, ClientProjectActivity::VISIBILITY_CLIENT])
            ->selectRaw('COUNT(*) as activity_count')
            ->groupBy('client_project_id')
            ->get()->keyBy('client_project_id');

        return $projects->mapWithKeys(function (ClientProject $project) use ($month, $rows): array {
            $row = $rows->get($project->getKey());
            $allocated = $project->monthly_hour_limit_minutes;
            $used = (int) ($row?->used_minutes ?? 0);

            return [$project->getKey() => [
                'month' => $month->format('Y-m'),
                'allocated_minutes' => $allocated,
                'used_minutes' => $used,
                'remaining_minutes' => $allocated === null ? null : max(0, $allocated - $used),
                'overage_minutes' => $allocated === null ? 0 : max(0, $used - $allocated),
                'usage_percentage' => $allocated === null ? null : ($allocated === 0 ? ($used > 0 ? 100 : 0) : (int) round(($used / $allocated) * 100)),
                'is_exceeded' => $allocated !== null && $used > $allocated,
                'published_client_activity_count' => (int) ($row?->published_count ?? 0),
                'admin_activity_count' => (int) ($row?->activity_count ?? 0),
            ]];
        });
    }
}
