<?php

namespace Tests\Unit;

use App\Support\FormNumber;
use Tests\TestCase;

class FormNumberTest extends TestCase
{
    public function test_persian_and_arabic_presentation_is_canonicalized_without_float_conversion(): void
    {
        $this->assertSame('1250000.5', FormNumber::canonicalize('۱٬۲۵۰٬۰۰۰٫۵'));
        $this->assertSame('-001250.50', FormNumber::canonicalize('-٠٠١,٢٥٠.٥٠'));
    }

    public function test_thousands_formatting_preserves_decimal_text_exactly(): void
    {
        $this->assertSame('1,250,000.50', FormNumber::format('1250000.50', true));
        $this->assertSame('1250000.50', FormNumber::format('1250000.50', false));
        $this->assertSame('12.567', FormNumber::format('12.567', true));
    }
}
