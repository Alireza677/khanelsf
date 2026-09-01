<?php

namespace App\Support;

final class FormChoicePresentation
{
    private const SHORT_MAX_LENGTH = 25;

    private const MEDIUM_MAX_LENGTH = 50;

    public static function sizeClass(string $label): string
    {
        $length = mb_strlen(trim($label));

        if ($length <= self::SHORT_MAX_LENGTH) {
            return 'choice-grid-item--short';
        }

        if ($length <= self::MEDIUM_MAX_LENGTH) {
            return 'choice-grid-item--medium';
        }

        return 'choice-grid-item--long';
    }
}
