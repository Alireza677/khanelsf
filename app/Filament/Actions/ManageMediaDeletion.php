<?php

namespace App\Filament\Actions;

use App\Filament\Resources\MediaResource;
use App\Media\MediaDependencyService;
use App\Media\MediaInUseException;
use App\Models\Media;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Support\Enums\MaxWidth;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ManageMediaDeletion
{
    public static function make(): Action
    {
        return Action::make('delete')
            ->label('حذف / مدیریت وابستگی‌ها')->icon('heroicon-o-trash')->color('danger')
            ->visible(fn (Media $record): bool => auth()->user()?->isAdmin() && auth()->user()?->isActive() && MediaResource::canDelete($record))
            ->modalHeading('مدیریت استفاده و حذف رسانه')->modalWidth(MaxWidth::FiveExtraLarge)
            ->modalSubmitActionLabel('اجرای تغییرات تأییدشده')->modalCancelActionLabel('انصراف')
            ->fillForm(['operation' => 'replace', 'dependencies' => [], 'confirmed' => false])
            ->form([
                Forms\Components\Placeholder::make('usage_report')->label('')
                    ->content(fn (Media $record) => view('filament.media.dependencies', ['record' => $record, 'dependencies' => app(MediaDependencyService::class)->modalReport($record)])),
                Forms\Components\ViewField::make('replacement_media_id')
                    ->label('انتخاب تصویر جایگزین')->view('filament.forms.components.media-library-picker')->live()
                    ->viewData(fn (Media $record): array => [
                        'images' => self::replacementImages($record),
                        'pickerHeading' => 'انتخاب تصویر جایگزین',
                        'pickerButtonLabel' => 'انتخاب تصویر جایگزین',
                        'previewHeight' => 112,
                    ])
                    ->visible(fn (Media $record): bool => str_starts_with((string) $record->mime_type, 'image/') && app(MediaDependencyService::class)->modalReport($record) !== [])
                    ->required(fn (Get $get): bool => $get('operation') !== 'delete'),
                Forms\Components\CheckboxList::make('dependencies')->label('محل‌های انتخاب‌شده برای جایگزینی')
                    ->options(fn (Media $record): array => collect(app(MediaDependencyService::class)->modalReport($record))->where('replaceable', true)->mapWithKeys(fn ($row) => [$row['key'] => $row['title'].' — '.$row['label']])->all())
                    ->live()->columns(1)->visible(fn (Media $record): bool => app(MediaDependencyService::class)->modalReport($record) !== []),
                Forms\Components\Radio::make('operation')->label('عملیات موردنظر')->live()->required()
                    ->options(fn (Media $record): array => app(MediaDependencyService::class)->modalReport($record) === []
                        ? ['delete' => 'فقط حذف رسانه بدون وابستگی']
                        : ['replace' => 'فقط جایگزینی در محل‌های انتخاب‌شده', 'replace_delete' => 'جایگزینی و حذف تصویر قبلی'])
                    ->disableOptionWhen(fn (string $value, Get $get, Media $record): bool => $value === 'replace_delete'
                        && array_diff(array_column(app(MediaDependencyService::class)->modalReport($record), 'key'), $get('dependencies') ?? []) !== [])
                    ->afterStateHydrated(function (Forms\Set $set, Media $record): void {
                        if (app(MediaDependencyService::class)->modalReport($record) === []) {
                            $set('operation', 'delete');
                        }
                    }),
                Forms\Components\Placeholder::make('change_summary')->label('خلاصه پیش از اجرا')
                    ->content(fn (Get $get, Media $record) => view('filament.media.replacement-summary', [
                        'source' => $record, 'replacement' => self::replacementPreview($get('replacement_media_id')),
                        'selected' => collect(app(MediaDependencyService::class)->modalReport($record))->whereIn('key', $get('dependencies') ?? [])->all(),
                        'operation' => $get('operation'),
                        'remaining' => count(array_diff(array_column(app(MediaDependencyService::class)->modalReport($record), 'key'), $get('dependencies') ?? [])),
                    ])),
                Forms\Components\Checkbox::make('confirmed')->label('خلاصه را بررسی کردم و این تغییرات را تأیید می‌کنم.')->rules(['accepted']),
            ])
            ->action(function (Media $record, array $data): void {
                try {
                    app(MediaDependencyService::class)->apply($record, filled($data['replacement_media_id'] ?? null) ? (int) $data['replacement_media_id'] : null, $data['dependencies'] ?? [], $data['operation']);
                } catch (MediaInUseException) {
                    throw ValidationException::withMessages(['operation' => 'حذف انجام نشد: هنوز ارجاع باقی مانده است. فقط جایگزینی را انتخاب کنید یا تمام وابستگی‌ها را رفع کنید.']);
                } catch (QueryException $exception) {
                    report($exception);
                    throw ValidationException::withMessages(['operation' => 'ارجاع‌ها هم‌زمان تغییر کردند یا ذخیره ناموفق بود؛ تغییرات این عملیات بازگردانده شد. مودال را دوباره باز کنید.']);
                } catch (\Throwable $exception) {
                    if ($exception instanceof ValidationException || $exception instanceof HttpExceptionInterface) {
                        throw $exception;
                    }
                    report($exception);
                    throw ValidationException::withMessages(['operation' => 'عملیات ناموفق بود و تغییرات بازگردانده شدند؛ دوباره تلاش کنید.']);
                }
                self::notify($data['operation'] === 'replace' ? 'جایگزینی انجام شد؛ تصویر قبلی حذف نشده است.' : 'حذف رسانه با موفقیت انجام شد.');
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('delete')->label('حذف گروهی')->icon('heroicon-o-trash')->color('danger')
            ->visible(fn (): bool => auth()->user()?->isAdmin() && auth()->user()?->isActive() && MediaResource::canDeleteAny())
            ->requiresConfirmation()->modalHeading('بررسی وابستگی‌ها پیش از حذف گروهی')
            ->modalDescription('اگر حتی یک رسانه وابستگی داشته باشد، هیچ‌کدام حذف نمی‌شوند. برای جایگزینی، اکشن مدیریت همان رسانه را باز کنید.')
            ->modalContent(fn (Collection $records) => view('filament.media.bulk-dependencies', ['records' => $records]))
            ->modalSubmitActionLabel('حذف رسانه‌های بدون وابستگی')->modalCancelActionLabel('انصراف')
            ->action(function (Collection $records): void {
                try {
                    app(MediaDependencyService::class)->deleteMany($records->modelKeys());
                } catch (MediaInUseException|QueryException $exception) {
                    Notification::make()->warning()->title('حذف گروهی انجام نشد؛ وابستگی‌ها را برای هر رسانه مدیریت کنید.')->send();

                    return;
                }
                self::notify();
            })->deselectRecordsAfterCompletion();
    }

    private static function notify(string $message = 'عملیات رسانه با موفقیت انجام شد.'): void
    {
        if (app(MediaDependencyService::class)->cleanupFailures !== []) {
            Notification::make()->warning()->title('تغییرات ثبت شدند؛ پاک‌سازی برخی فایل‌ها ناموفق بود و در گزارش خطا ثبت شد.')->send();
        } else {
            Notification::make()->success()->title($message)->send();
        }
    }

    private static function replacementImages(Media $source): array
    {
        $items = MediaResource::mediaLibraryImageItems();
        $allowed = Media::query()->whereIn('id', array_column($items, 'id'))->get()
            ->filter(fn (Media $media): bool => MediaResource::canView($media))->modelKeys();

        return array_values(array_filter($items, fn ($image): bool => (int) $image['id'] !== (int) $source->id && in_array((int) $image['id'], $allowed, true)));
    }

    private static function replacementPreview(int|string|null $id): ?Media
    {
        $media = Media::reusableImage($id);

        return $media && MediaResource::canView($media) ? $media : null;
    }
}
