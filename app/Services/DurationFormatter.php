<?php

namespace App\Services;

class DurationFormatter
{
    public function format(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        $hours = intdiv($minutes, 60);
        $remainder = $minutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.' ساعت';
        }

        if ($remainder > 0 || $parts === []) {
            $parts[] = $remainder.' دقیقه';
        }

        return implode(' و ', $parts);
    }
}
