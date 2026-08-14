<?php

namespace App\Services;

use App\Models\Service;

final class ServiceActivityCatalog
{
    public function __construct(private readonly ServiceSettings $settings) {}

    public function enabled(): bool
    {
        return $this->settings->activityCatalogEnabled();
    }

    public function options(): array
    {
        if (! $this->enabled()) {
            return [];
        }

        return Service::query()->availableForActivities()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function find(int|string|null $id): ?Service
    {
        if (! $this->enabled() || ! $id) {
            return null;
        }

        return Service::query()->availableForActivities()->find((int) $id);
    }
}
