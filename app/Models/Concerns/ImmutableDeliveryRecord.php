<?php

namespace App\Models\Concerns;

use Illuminate\Validation\ValidationException;

trait ImmutableDeliveryRecord
{
    public static function bootImmutableDeliveryRecord(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['record' => 'سابقه منتشرشده قابل تغییر نیست؛ نسخه جدید ثبت کنید.']));
        static::deleting(fn () => throw ValidationException::withMessages(['record' => 'حذف سابقه تحویل مجاز نیست.']));
    }
}
