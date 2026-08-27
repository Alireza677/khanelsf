<?php

namespace App\Enums;

enum NetworkLocationType: string
{
    case Partner = 'partner';
    case Agency = 'agency';
    case Branch = 'branch';
    case Dealer = 'dealer';
    case ServiceCenter = 'service_center';

    public function label(): string
    {
        return match ($this) {
            self::Partner => 'همکار', self::Agency => 'نمایندگی', self::Branch => 'شعبه',
            self::Dealer => 'عامل فروش', self::ServiceCenter => 'مرکز خدمات',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $type) => [$type->value => $type->label()])->all();
    }
}
