<?php

namespace App\Support;

final class PublicOrderCode
{
    /** A presentation identifier, never an access credential or route key. */
    public static function generate(): string
    {
        // Skip visually ambiguous characters; DB uniqueness is the final arbiter.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = 'K-';

        for ($index = 0; $index < 7; $index++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    public static function forLegacyId(int $id): string
    {
        return 'K-LG'.str_pad(strtoupper(base_convert((string) $id, 10, 36)), 4, '0', STR_PAD_LEFT);
    }
}
