<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Support\PersianDate;

class ClientProjectPresenter
{
    public function present(ClientProject $project): array
    {
        return [
            'id' => $project->getKey(),
            'title' => $project->title,
            'description' => $project->description,
            'type' => $project->type,
            'status' => $project->status,
            'status_label' => $this->statusLabels()[$project->status] ?? $project->status,
            'progress' => max(0, min(100, $project->progress)),
            'monthly_hour_limit_minutes' => $project->monthly_hour_limit_minutes,
            'start_date' => PersianDate::date($project->start_date),
            'end_date' => PersianDate::date($project->end_date),
        ];
    }

    private function statusLabels(): array
    {
        return [
            ClientProject::STATUS_DRAFT => 'پیش‌نویس',
            ClientProject::STATUS_ACTIVE => 'فعال',
            ClientProject::STATUS_PAUSED => 'متوقف‌شده',
            ClientProject::STATUS_COMPLETED => 'تکمیل‌شده',
            ClientProject::STATUS_CANCELLED => 'لغوشده',
        ];
    }
}
