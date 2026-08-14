<?php

namespace App\Services;

use App\Enums\ServiceUnit;

final class ServiceSettings
{
    public function __construct(private readonly SettingsService $settings) {}

    public function publicEnabled(): bool
    {
        return $this->boolean('public_services_enabled', true);
    }

    public function activityCatalogEnabled(): bool
    {
        return $this->boolean('service_activity_catalog_enabled', false);
    }

    public function pricingEnabled(): bool
    {
        return $this->boolean('service_pricing_enabled', false);
    }

    public function formSectionEnabled(string $section): bool
    {
        return $this->boolean("service_form_{$section}_enabled", true);
    }

    public function allowedUnits(): array
    {
        $value = $this->settings->get('service_allowed_units');
        $units = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($units)) {
            return ServiceUnit::values();
        }

        return array_values(array_intersect(ServiceUnit::values(), $units));
    }

    public function allowedUnitOptions(): array
    {
        return array_intersect_key(ServiceUnit::options(), array_flip($this->allowedUnits()));
    }

    private function boolean(string $key, bool $fallback): bool
    {
        return filter_var($this->settings->get($key, $fallback), FILTER_VALIDATE_BOOLEAN);
    }
}
