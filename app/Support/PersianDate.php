<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Morilog\Jalali\Jalalian;
use Throwable;

final class PersianDate
{
    public static function date(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'Y/m/d');
    }

    public static function dateTime(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'Y/m/d - H:i');
    }

    public static function dateWithWeekday(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'l j F Y');
    }

    public static function monthYear(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'F Y');
    }

    public static function year(DateTimeInterface|string|null $value): ?string
    {
        return self::format($value, 'Y');
    }

    public static function human(DateTimeInterface|string|null $value): ?string
    {
        $date = self::carbon($value);

        return $date?->locale('fa')->diffForHumans();
    }

    public static function toGregorianDate(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = str_replace('-', '/', self::latinDigits(trim($value)));

        try {
            return Jalalian::fromFormat('Y/m/d', $normalized, self::timezone())->toCarbon()->format('Y-m-d');
        } catch (Throwable $exception) {
            throw new InvalidArgumentException("Invalid Jalali date [{$value}].", previous: $exception);
        }
    }

    public static function digits(string|int|float|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtr((string) $value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    private static function format(DateTimeInterface|string|null $value, string $format): ?string
    {
        $date = self::carbon($value);

        return $date ? self::digits(Jalalian::fromDateTime($date, self::timezone())->format($format)) : null;
    }

    private static function carbon(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse($value, self::timezone());

        return $date->setTimezone(self::timezone());
    }

    private static function latinDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(config('app.timezone'));
    }
}
