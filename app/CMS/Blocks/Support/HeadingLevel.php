<?php

namespace App\CMS\Blocks\Support;

use Filament\Forms\Components\Select;

final class HeadingLevel
{
    public const DEFAULT = 'h2';

    public const LEVELS = ['h1', 'h2', 'h3'];

    public static function normalize(mixed $value, string $default = self::DEFAULT): string
    {
        $default = in_array($default, self::LEVELS, true) ? $default : self::DEFAULT;

        return is_string($value) && in_array(strtolower($value), self::LEVELS, true)
            ? strtolower($value)
            : $default;
    }

    public static function field(
        string $path = 'settings.heading_tag',
        string $label = 'تگ عنوان',
        string $default = self::DEFAULT,
    ): Select {
        return Select::make($path)
            ->label($label)
            ->options(array_combine(self::LEVELS, array_map('strtoupper', self::LEVELS)))
            ->default(self::normalize($default))
            ->native(false);
    }
}
