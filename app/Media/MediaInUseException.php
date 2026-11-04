<?php

namespace App\Media;

use RuntimeException;

final class MediaInUseException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('این رسانه هنوز محل استفاده دارد؛ ابتدا وابستگی‌های آن را مدیریت کنید.');
    }
}
