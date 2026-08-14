<?php

namespace App\Enums;

enum ServicePricingMode: string
{
    case Hourly = 'hourly';
    case PerUnit = 'per_unit';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'ساعتی',
            self::PerUnit => 'براساس واحد',
            self::Fixed => 'ثابت / پروژه',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $mode): array => [$mode->value => $mode->label()])->all();
    }
}
