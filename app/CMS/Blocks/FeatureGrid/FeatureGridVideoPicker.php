<?php

namespace App\CMS\Blocks\FeatureGrid;

use App\Models\Video;
use App\Services\VideoLibrarySettings;
use App\Services\VideoQueryService;
use Closure;
use Filament\Forms\Components\Select;

final class FeatureGridVideoPicker
{
    public static function make(string $name): Select
    {
        return Select::make($name)->label('ویدئو از کتابخانه')->searchable()->allowHtml()
            ->getSearchResultsUsing(function (string $search): array {
                if (! app(VideoLibrarySettings::class)->enabled()) {
                    return [];
                }

                return app(VideoQueryService::class)->archiveQuery()->where('title', 'like', '%'.addcslashes($search, '%_\\').'%')
                    ->limit(30)->get()->mapWithKeys(fn (Video $video): array => [$video->id => self::label($video)])->all();
            })
            ->getOptionLabelUsing(function ($value): ?string {
                if (blank($value)) {
                    return null;
                }
                $video = app(VideoLibrarySettings::class)->enabled()
                    ? app(VideoQueryService::class)->archiveQuery()->whereKey($value)->first() : null;

                return $video ? self::label($video) : 'ویدئو در دسترس نیست؛ ویدئوی منتشرشده دیگری انتخاب کنید.';
            })
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

    private static function label(Video $video): string
    {
        return '<span class="flex items-center gap-2"><img src="'.e($video->thumbnailUrl()).'" alt="" width="48" height="27" loading="lazy" referrerpolicy="no-referrer"><span>'.e($video->title).'</span></span>';
    }
}
