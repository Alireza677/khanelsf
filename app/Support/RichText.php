<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

final class RichText
{
    public static function render(mixed $value): HtmlString
    {
        if (! is_string($value) || trim($value) === '') {
            return new HtmlString('');
        }

        $value = trim($value);
        $html = $value === strip_tags($value)
            ? self::legacyPlainTextToHtml($value)
            : $value;

        return new HtmlString(Str::sanitizeHtml($html));
    }

    private static function legacyPlainTextToHtml(string $value): string
    {
        $paragraphs = preg_split('/(?:\r\n|\r|\n){2,}/', $value) ?: [];

        return collect($paragraphs)
            ->map(fn (string $paragraph): string => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')
            ->implode('');
    }
}
