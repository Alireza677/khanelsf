<?php

namespace App\CMS\Blocks\FeatureGrid;

use App\CMS\Actions\Filament\ReferenceTargetPicker;
use App\Services\VideoLibrarySettings;
use App\Services\VideoQueryService;
use Closure;

final class FeatureGridVideoPicker
{
    public static function make(string $name): ReferenceTargetPicker
    {
        return ReferenceTargetPicker::make($name)->label('ویدئو از کتابخانه')->targetType('video')
            ->rules([fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                if (filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false
                    || ! app(VideoLibrarySettings::class)->enabled()
                    || ! app(VideoQueryService::class)->archiveQuery()->whereKey($value)->exists()) {
                    $fail('یک ویدئوی منتشرشده و قابل‌دسترسی از کتابخانه فعال انتخاب کنید.');
                }
            }])
            ->helperText('فقط ویدئوهای منتشرشده با زمان انتشار رسیده از کتابخانه فعال قابل انتخاب‌اند.')
            ->required();
    }
}
