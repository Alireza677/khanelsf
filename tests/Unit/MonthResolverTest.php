<?php

namespace Tests\Unit;

use App\Services\MonthResolver;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonthResolverTest extends TestCase
{
    #[DataProvider('jalaliMonthBoundaries')]
    public function test_it_resolves_complete_jalali_months_to_gregorian_ranges(
        string $month,
        string $expectedStart,
        string $expectedEnd,
    ): void {
        $range = app(MonthResolver::class)->resolveRange($month);

        $this->assertSame($expectedStart, $range['start']->toDateString());
        $this->assertSame($expectedEnd, $range['end']->toDateString());
        $this->assertSame($month, $range['value']);
    }

    public static function jalaliMonthBoundaries(): array
    {
        return [
            '31-day Shahrivar' => ['1405-06', '2026-08-23', '2026-09-22'],
            '30-day Mehr' => ['1405-07', '2026-09-23', '2026-10-22'],
            '29-day Esfand' => ['1404-12', '2026-02-20', '2026-03-20'],
            'leap Esfand before Nowruz' => ['1399-12', '2021-02-19', '2021-03-20'],
            'Farvardin after Nowruz' => ['1400-01', '2021-03-21', '2021-04-20'],
        ];
    }

    public function test_default_is_the_current_jalali_month_and_persian_digits_are_accepted(): void
    {
        CarbonImmutable::setTestNow('2026-08-27 12:00:00');

        try {
            $resolver = app(MonthResolver::class);

            $this->assertSame('1405-06', $resolver->resolveRange(null)['value']);
            $this->assertSame('1405-06', $resolver->resolveRange('۱۴۰۵-۰۶')['value']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
