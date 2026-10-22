<?php

namespace App\Services\Video;

use InvalidArgumentException;

final class VideoDuration
{
    public const MAX_SECONDS = 2147483647;

    public static function parse(?string $value): ?int
    {
        if (blank($value)) {
            return null;
        }

        $value = strtr(trim($value), array_combine(
            preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('01234567890123456789'),
        ));
        if (preg_match('/^(\d{1,7}):([0-5]\d)(?::([0-5]\d))?$/D', $value, $parts)) {
            $seconds = isset($parts[3])
                ? (int) $parts[1] * 3600 + (int) $parts[2] * 60 + (int) $parts[3]
                : (int) $parts[1] * 60 + (int) $parts[2];
            if ($seconds <= self::MAX_SECONDS) {
                return $seconds;
            }
        }

        throw new InvalidArgumentException('مدت را به صورت mm:ss یا hh:mm:ss وارد کنید.');
    }

    public static function format(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        return $seconds >= 3600
            ? sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60)
            : sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
