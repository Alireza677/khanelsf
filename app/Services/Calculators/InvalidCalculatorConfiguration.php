<?php

namespace App\Services\Calculators;

use App\Models\Form;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class InvalidCalculatorConfiguration extends RuntimeException
{
    public const PUBLIC_MESSAGE = 'محاسبهٔ این فرم در حال حاضر امکان‌پذیر نیست. لطفاً با پشتیبانی تماس بگیرید.';

    public static function forForm(Form $form, string $detail): self
    {
        $message = 'تنظیمات محاسبه‌گر معتبر نیست: '.$detail;
        Log::warning($message, ['form_id' => $form->getKey(), 'calculator_identifier' => $form->calculator_identifier]);

        return new self($message);
    }
}
