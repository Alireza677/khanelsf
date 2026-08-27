<?php

namespace App\Enums;

enum ClientProjectCycleStatus: string
{
    case Active = 'active';
    case Overdue = 'overdue';
    case Completed = 'completed';
    case Invoiced = 'invoiced';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'فعال', self::Overdue => 'عقب‌افتاده', self::Completed => 'تکمیل‌شده', self::Invoiced => 'فاکتورشده'
        };
    }
}
