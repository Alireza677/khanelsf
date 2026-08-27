<?php

namespace App\Services;

use App\Support\PersianDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Morilog\Jalali\Jalalian;

class MonthResolver
{
    /** @return array{start: CarbonImmutable, end: CarbonImmutable, value: string, year: int, month: int} */
    public function resolveRange(?string $value): array
    {
        $value = $value ?: Jalalian::fromDateTime(CarbonImmutable::now())->format('Y-m');
        $value = str_replace('/', '-', PersianDate::latinDigits($value));

        Validator::make(['month' => $value], [
            'month' => ['required', 'regex:/^(13(?:5[0-9]|[6-9][0-9])|14[0-9]{2}|1500)-(0[1-9]|1[0-2])$/'],
        ])->validate();

        [$year, $month] = array_map('intval', explode('-', $value));
        $start = CarbonImmutable::instance(
            Jalalian::fromFormat('Y-m-d', sprintf('%04d-%02d-01', $year, $month))->toCarbon(),
        )->startOfDay();
        $end = CarbonImmutable::instance(
            (new Jalalian($year, $month, 1))->addMonths(1)->toCarbon(),
        )->subDay()->endOfDay();

        return compact('start', 'end', 'value', 'year', 'month');
    }

    public function resolve(?string $value): CarbonImmutable
    {
        return $this->resolveRange($value)['start'];
    }
}
