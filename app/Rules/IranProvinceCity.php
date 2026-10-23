<?php

namespace App\Rules;

use App\Services\IranLocations;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IranProvinceCity implements ValidationRule
{
    public function __construct(private readonly bool $required = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->required && IranLocations::isEmpty($value)) {
            return;
        }

        if (app(IranLocations::class)->pair($value) === null) {
            $fail('استان و شهر را به‌صورت کامل و معتبر انتخاب کنید؛ شهر باید متعلق به استان انتخاب‌شده باشد.');
        }
    }
}
