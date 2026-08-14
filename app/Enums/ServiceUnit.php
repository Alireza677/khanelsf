<?php

namespace App\Enums;

enum ServiceUnit: string
{
    case Hour = 'hour';
    case Count = 'count';
    case Session = 'session';
    case Meter = 'meter';
    case SquareMeter = 'square_meter';
    case Day = 'day';
    case Kilogram = 'kilogram';
    case Fixed = 'fixed';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Hour => 'ساعت',
            self::Count => 'عدد',
            self::Session => 'جلسه',
            self::Meter => 'متر',
            self::SquareMeter => 'متر مربع',
            self::Day => 'روز',
            self::Kilogram => 'کیلوگرم',
            self::Fixed => 'ثابت / پروژه',
            self::Custom => 'واحد سفارشی',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $unit): array => [$unit->value => $unit->label()])->all();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
