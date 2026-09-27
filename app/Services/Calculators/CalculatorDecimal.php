<?php

namespace App\Services\Calculators;

/** Exact decimal arithmetic, using the project's existing BCMath convention. */
final class CalculatorDecimal
{
    public static function value(int|float|string $value): string
    {
        $value = is_float($value) ? json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR) : trim((string) $value);
        if (stripos($value, 'e') !== false) {
            [$mantissa, $exponent] = preg_split('/e/i', $value);
            $parts = explode('.', ltrim($mantissa, '+'));
            $digits = implode('', $parts);
            $point = strlen($parts[0]) + (int) $exponent;
            $value = $point <= 0 ? '0.'.str_repeat('0', -$point).$digits
                : ($point >= strlen($digits) ? str_pad($digits, $point, '0') : substr($digits, 0, $point).'.'.substr($digits, $point));
        }

        return self::clean(bcadd($value, '0', self::scale($value)));
    }

    public static function add(string $a, string $b): string
    {
        return self::clean(bcadd($a, $b, max(self::scale($a), self::scale($b))));
    }

    public static function multiply(string $a, string $b): string
    {
        return self::clean(bcmul($a, $b, self::scale($a) + self::scale($b)));
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, max(self::scale($a), self::scale($b)));
    }

    public static function percentage(string $score, string $maximum): string
    {
        return self::rounded(bcdiv(self::multiply($score, '100'), $maximum, 3));
    }

    public static function rounded(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    private static function scale(string $value): int
    {
        return str_contains($value, '.') ? strlen(explode('.', $value, 2)[1]) : 0;
    }

    private static function clean(string $value): string
    {
        return str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
    }
}
