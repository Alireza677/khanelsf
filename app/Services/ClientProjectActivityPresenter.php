<?php

namespace App\Services;

use App\Models\ClientProjectActivity;

class ClientProjectActivityPresenter
{
    public function __construct(private readonly DurationFormatter $durations) {}

    public function present(ClientProjectActivity $activity): array
    {
        return [
            'id' => $activity->getKey(),
            'title' => $activity->title,
            'description' => $activity->description,
            'activity_date' => $activity->activity_date->format('Y/m/d'),
            'duration' => $this->durations->format($activity->duration_minutes),
            'project_title' => $activity->project?->title,
            'status_label' => 'منتشرشده',
        ];
    }
}
