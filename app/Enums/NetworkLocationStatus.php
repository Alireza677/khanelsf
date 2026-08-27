<?php

namespace App\Enums;

enum NetworkLocationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string { return $this === self::Active ? 'فعال' : 'غیرفعال'; }
    public static function options(): array { return ['active' => 'فعال', 'inactive' => 'غیرفعال']; }
}
