<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Quantity/value precision is separate from measurement units and display formatting. */
final class ServiceDeliveryNumber
{
    public const FORMATS = ['integer' => 'عدد صحیح', 'decimal' => 'عدد اعشاری'];

    public const INTEGER_INPUT = '/^-?\d{1,14}(?:\.0{1,4})?$/';

    public static function format(string|int|null $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }
        $value = (string) $value;
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $parts)) {
            return $value;
        }
        $whole = ltrim($parts[2], '0') ?: '0';
        $fraction = rtrim($parts[3] ?? '', '0');
        $number = $whole.($fraction !== '' ? '.'.$fraction : '');

        return ($parts[1] === '-' && $number !== '0' ? '-' : '').$number;
    }

    public static function quantityFormat(?string $unit): string
    {
        return in_array(mb_strtolower(trim($unit ?? '')), ['count', 'counts', 'session', 'sessions', 'عدد', 'جلسه', 'نوبت'], true) ? 'integer' : 'decimal';
    }

    public static function isInteger(string|int $value): bool
    {
        return preg_match('/^-?\d+(?:\.0+)?$/', (string) $value) === 1;
    }

    public static function assertPrecision(string|int $value, string $format, string $field): void
    {
        if ($format === 'integer' && ! self::isInteger($value)) {
            throw ValidationException::withMessages([$field => 'برای این مقدار فقط عدد صحیح مجاز است؛ بخش اعشاری را حذف کنید.']);
        }
    }
}
