<?php

namespace Tests\Unit;

use App\Support\PersianDate;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Tests\TestCase;

class PersianDateTest extends TestCase
{
    public function test_known_gregorian_date_is_formatted_as_jalali_and_round_trips(): void
    {
        $gregorian = CarbonImmutable::parse('2026-08-20', config('app.timezone'));

        $this->assertSame('۱۴۰۵/۰۵/۲۹', PersianDate::date($gregorian));
        $this->assertSame('2026-08-20', PersianDate::toGregorianDate('۱۴۰۵/۰۵/۲۹'));
        $this->assertSame('2026-08-20', PersianDate::toGregorianDate('1405/05/29'));
    }

    public function test_null_is_safe_for_every_presentation(): void
    {
        $this->assertNull(PersianDate::date(null));
        $this->assertNull(PersianDate::dateTime(null));
        $this->assertNull(PersianDate::human(null));
        $this->assertNull(PersianDate::toGregorianDate(null));
    }

    public function test_jalali_leap_year_and_new_year_boundaries(): void
    {
        $this->assertSame('2021-03-20', PersianDate::toGregorianDate('۱۳۹۹/۱۲/۳۰'));
        $this->assertSame('2021-03-21', PersianDate::toGregorianDate('۱۴۰۰/۰۱/۰۱'));
        $this->assertSame('۱۳۹۹/۱۲/۳۰', PersianDate::date('2021-03-20'));
        $this->assertSame('۱۴۰۰/۰۱/۰۱', PersianDate::date('2021-03-21'));
    }

    public function test_summer_month_boundary_is_correct(): void
    {
        $this->assertSame('۱۴۰۵/۰۶/۳۱', PersianDate::date('2026-09-22'));
        $this->assertSame('۱۴۰۵/۰۷/۰۱', PersianDate::date('2026-09-23'));
    }

    public function test_invalid_jalali_input_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PersianDate::toGregorianDate('not-a-date');
    }

    public function test_datetime_keeps_application_timezone(): void
    {
        config()->set('app.timezone', 'Asia/Tehran');

        $this->assertSame(
            '۱۴۰۵/۰۵/۲۹ - ۰۷:۰۰',
            PersianDate::dateTime(CarbonImmutable::parse('2026-08-20 03:30:00', 'UTC')),
        );
    }
}
