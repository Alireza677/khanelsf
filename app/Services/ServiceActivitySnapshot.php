<?php

namespace App\Services;

use App\Enums\ServiceUnit;
use App\Models\ClientProjectActivity;
use App\Models\Service;

final class ServiceActivitySnapshot
{
    public function __construct(
        private readonly ServiceActivityPricingCalculator $calculator,
        private readonly SettingsService $settings,
    ) {}

    public function from(Service $service, int $durationMinutes, string|int|null $quantity = null): array
    {
        $pricingMode = $service->pricing_mode?->value;
        $unitValue = $service->unit?->value;
        $pricingEnabled = $this->settings->get('service_pricing_enabled', false);
        $pricingEnabled = filter_var($pricingEnabled, FILTER_VALIDATE_BOOLEAN);
        $pricing = $this->calculator->calculate(
            (string) $pricingMode,
            $pricingEnabled ? $service->default_unit_price : null,
            $durationMinutes,
            $quantity,
        );
        $unit = ServiceUnit::tryFrom((string) $unitValue);

        return [
            'service_id' => $service->getKey(),
            'service_name_snapshot' => $service->name,
            'service_unit_snapshot' => $unitValue,
            'service_unit_label_snapshot' => $unit === ServiceUnit::Custom ? $service->custom_unit_label : $unit?->label(),
            'pricing_mode_snapshot' => $pricingMode,
            'currency_snapshot' => $pricingEnabled
                ? ($service->currency_code ?: strtoupper((string) $this->settings->get('default_service_currency', 'IRT')))
                : null,
            'unit_price_snapshot' => $pricingEnabled ? $service->default_unit_price : null,
            'quantity' => $pricing['quantity'],
            'total_amount' => $pricing['total_amount'],
        ];
    }

    public function recalculate(ClientProjectActivity $activity, int $durationMinutes, string|int|null $quantity): array
    {
        $pricing = $this->calculator->calculate(
            (string) $activity->pricing_mode_snapshot,
            $activity->unit_price_snapshot,
            $durationMinutes,
            $quantity,
        );

        return [
            'quantity' => $pricing['quantity'],
            'total_amount' => $pricing['total_amount'],
        ];
    }
}
