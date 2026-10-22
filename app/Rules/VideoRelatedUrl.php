<?php

namespace App\Rules;

use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Validation\ActionDestinationValidator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class VideoRelatedUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || app(ActionDestinationValidator::class)
            ->validate(new ActionDestination(type: 'custom_url', value: $value))->isInvalid()) {
            $fail('لینک مرتبط معتبر نیست. از مسیر داخلی یا آدرس HTTP/HTTPS استفاده کنید.');
        }
    }
}
