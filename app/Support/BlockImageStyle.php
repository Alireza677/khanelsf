<?php

namespace App\Support;

class BlockImageStyle
{
    private const UNITS = ['px', '%'];

    private const FITS = [
        'normal' => null,
        'cover' => 'cover',
        'contain' => 'contain',
    ];

    public static function imageVariables(array $data, string $prefix = 'image'): string
    {
        return self::variables([
            '--block-image-width' => self::length($data, "{$prefix}_width"),
            '--block-image-height' => self::length($data, "{$prefix}_height"),
            '--block-image-fit' => self::fit($data["{$prefix}_fit"] ?? null),
            '--block-image-mobile-width' => self::length($data, "{$prefix}_mobile_width"),
            '--block-image-mobile-height' => self::length($data, "{$prefix}_mobile_height"),
            '--block-image-mobile-fit' => self::fit($data["{$prefix}_mobile_fit"] ?? null),
        ]);
    }

    public static function backgroundVariables(array $data, string $prefix = 'image'): string
    {
        return self::variables([
            '--block-background-size' => self::backgroundSize($data, $prefix),
            '--block-background-mobile-size' => self::backgroundSize($data, "{$prefix}_mobile"),
        ]);
    }

    private static function variables(array $variables): string
    {
        return collect($variables)
            ->filter()
            ->map(fn (string $value, string $key): string => "{$key}: {$value}")
            ->implode('; ');
    }

    private static function length(array $data, string $key): ?string
    {
        $value = trim((string) ($data["{$key}_value"] ?? ''));
        $unit = (string) ($data["{$key}_unit"] ?? '');

        if ($value === '' || ! in_array($unit, self::UNITS, true)) {
            return null;
        }

        $number = preg_replace('/[^0-9.]/', '', $value);

        if ($number === '' || ! is_numeric($number)) {
            return null;
        }

        if (str_contains($number, '.')) {
            $number = rtrim(rtrim($number, '0'), '.');
        }

        return $number.$unit;
    }

    private static function fit(?string $fit): ?string
    {
        return self::FITS[$fit ?: 'normal'] ?? null;
    }

    private static function backgroundSize(array $data, string $prefix): ?string
    {
        $fit = $data["{$prefix}_fit"] ?? null;

        if (in_array($fit, ['cover', 'contain'], true)) {
            return $fit;
        }

        $width = self::length($data, "{$prefix}_width");
        $height = self::length($data, "{$prefix}_height");

        if (! $width && ! $height) {
            return null;
        }

        return ($width ?: 'auto').' '.($height ?: 'auto');
    }
}
