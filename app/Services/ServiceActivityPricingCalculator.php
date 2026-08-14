<?php

namespace App\Services;

use App\Enums\ServicePricingMode;
use InvalidArgumentException;

final class ServiceActivityPricingCalculator
{
    public function calculate(string $pricingMode, string|int|null $unitPrice, int $durationMinutes, string|int|null $quantity): array
    {
        $mode = ServicePricingMode::tryFrom($pricingMode);
        $price = $this->decimal($unitPrice, 4, 'Unit price');

        if (! $mode) {
            return ['quantity' => null, 'effective_quantity' => null, 'total_amount' => null];
        }

        return match ($mode) {
            ServicePricingMode::Hourly => [
                'quantity' => null,
                'effective_quantity' => bcdiv((string) $durationMinutes, '60', 4),
                'total_amount' => $price === null ? null : $this->round(bcdiv(bcmul((string) $durationMinutes, $price, 8), '60', 8), 2),
            ],
            ServicePricingMode::PerUnit => $this->perUnit($price, $quantity),
            ServicePricingMode::Fixed => [
                'quantity' => null,
                'effective_quantity' => '1.0000',
                'total_amount' => $price === null ? null : $this->round($price, 2),
            ],
        };
    }

    private function perUnit(?string $price, string|int|null $quantity): array
    {
        $quantity = $this->decimal($quantity, 4, 'Quantity');

        if ($quantity === null || bccomp($quantity, '0', 4) <= 0) {
            throw new InvalidArgumentException('Quantity is required for per-unit pricing.');
        }

        return [
            'quantity' => $quantity,
            'effective_quantity' => $quantity,
            'total_amount' => $price === null ? null : $this->round(bcmul($price, $quantity, 8), 2),
        ];
    }

    private function decimal(string|int|null $value, int $scale, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);
        if (! preg_match('/^\d+(?:\.\d+)?$/', $value)) {
            throw new InvalidArgumentException("{$label} must be a non-negative decimal.");
        }

        return bcadd($value, '0', $scale);
    }

    private function round(string $value, int $scale): string
    {
        $increment = '0.'.str_repeat('0', $scale).'5';

        return bcadd($value, $increment, $scale);
    }
}
