<?php

namespace App\Rules;

use App\Support\FormUpload;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

final readonly class FormUploadRule implements ValidationRule
{
    public function __construct(
        private string $mode,
        private int $maxSizeMb,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('فایل بارگذاری‌شده معتبر نیست.');

            return;
        }

        if ($value->getSize() > $this->maxSizeMb * 1024 * 1024) {
            $fail("حجم فایل نباید بیشتر از {$this->maxSizeMb} مگابایت باشد.");

            return;
        }

        $extension = strtolower($value->getClientOriginalExtension());

        if (! in_array($extension, FormUpload::extensions($this->mode), true)) {
            $fail('فرمت فایل انتخاب‌شده مجاز نیست.');

            return;
        }

        if (! in_array((string) $value->getMimeType(), FormUpload::mimeTypes($this->mode), true)) {
            $fail('نوع محتوای فایل انتخاب‌شده مجاز نیست.');
        }
    }
}
