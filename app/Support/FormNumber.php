<?php

namespace App\Support;

final class FormNumber
{
    public static function canonicalize(mixed $value): mixed
    {
        if (! is_scalar($value)) {
            return $value;
        }

        $value = PersianDate::latinDigits(trim((string) $value));
        $value = str_replace('٫', '.', $value);

        return str_replace([',', '٬', ' ', "\u{00A0}", "\u{202F}"], '', $value);
    }

    public static function format(mixed $value, bool $thousandsSeparator): string
    {
        $canonical = self::canonicalize($value);

        if (! is_string($canonical)
            || preg_match('/^(?<sign>-?)(?<integer>\d+)(?:\.(?<decimal>\d*))?$/', $canonical, $matches) !== 1) {
            return is_scalar($value) ? (string) $value : '';
        }

        $integer = $thousandsSeparator
            ? strrev(implode(',', str_split(strrev($matches['integer']), 3)))
            : $matches['integer'];
        $decimal = array_key_exists('decimal', $matches) ? '.'.$matches['decimal'] : '';

        return $matches['sign'].$integer.$decimal;
    }
}
