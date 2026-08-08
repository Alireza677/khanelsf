<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

class MonthResolver
{
    public function resolve(?string $value): CarbonImmutable
    {
        if ($value === null || $value === '') {
            return CarbonImmutable::now()->startOfMonth();
        }

        Validator::make(['month' => $value], [
            'month' => ['required', 'date_format:Y-m', 'after_or_equal:2000-01', 'before_or_equal:2100-12'],
        ])->validate();

        return CarbonImmutable::createFromFormat('!Y-m', $value)->startOfMonth();
    }
}
