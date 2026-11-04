<?php

namespace App\Filament\Actions;

use App\Filament\Resources\MediaResource;
use App\Media\MediaDependencyService;
use App\Media\MediaInUseException;
use App\Models\Media;
use Filament\Actions\StaticAction;
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
            ->modalHeading('مدیریت استفاده و حذف رسانه')->modalWidth(MaxWidth::FourExtraLarge)
            ->extraModalWindowAttributes(['class' => 'king-media-management-modal', 'dir' => 'rtl'])
            ->stickyModalFooter()->closeModalByClickingAway(false)
            ->modalSubmitActionLabel('اجرای تغییرات')->modalCancelActionLabel('انصراف')
            ->modalSubmitAction(fn (StaticAction $action, $livewire): StaticAction => $action->color('primary')
                ->disabled(fn (): bool => ! self::readyToSubmit($livewire)))
            ->registerModalActions([
                Action::make('confirmDeletion')->requiresConfirmation()->color('danger')
                    ->modalHeading('تصویر قبلی حذف شود؟')
                    ->modalDescription('پس از بررسی دوبارهٔ وابستگی‌ها، تصویر قبلی حذف می‌شود. حذف فایل قابل بازگشت نیست.')
                    ->modalSubmitActionLabel('تأیید و اجرا')->modalCancelActionLabel('بازگشت')
                    ->closeModalByClickingAway(false)
                    ->action(function ($livewire): void {
                        $livewire->unmountTableAction(false, false);
                        $livewire->callMountedTableAction(['confirmed' => true]);
                    }),
            ])
            ->fillForm(['operation' => 'replace', 'dependencies' => []])
            ->form([
                Forms\Components\Placeholder::make('usage_report')->label('')
                    ->content(fn (Media $record) => view('filament.media.dependencies', ['record' => $record, 'dependencies' => app(MediaDependencyService::class)->modalReport($record)])),
                Forms\Components\Group::make([
                    Forms\Components\Placeholder::make('replacement_heading')->hiddenLabel()
                        ->content(fn () => view('filament.media.replacement-heading')),
                    Forms\Components\Group::make([
                        Forms\Components\CheckboxList::make('dependencies')->label('اعمال جایگزینی در')
                            ->options(fn (Media $record): array => self::replacementOptions($record))
                            ->live()->columns(2)->gridDirection('row')->required()->minItems(1)
                            ->extraAttributes(['class' => 'king-media-usage-options'])
                            ->visible(fn (Media $record): bool => self::replacementOptions($record) !== []),
                        Forms\Components\ViewField::make('replacement_media_id')
                            ->label('تصویر جایگزین')->hiddenLabel()->view('filament.forms.components.media-library-picker')->live()
                            ->viewData(fn (Media $record): array => [
                                'images' => self::replacementImages($record),
                                'pickerHeading' => 'انتخاب تصویر جایگزین',
                                'pickerButtonLabel' => 'انتخاب تصویر جایگزین',
                                'compact' => true,
                            ])
                            ->required(fn (Get $get): bool => $get('operation') !== 'delete'),
                    ])->extraAttributes(['class' => 'king-media-replacement-controls']),
                ])->extraAttributes(['class' => 'king-media-replacement-section'])
                    ->visible(fn (Media $record): bool => str_starts_with((string) $record->mime_type, 'image/') && app(MediaDependencyService::class)->modalReport($record) !== []),
                Forms\Components\Radio::make('operation')->label('عملیات نهایی')->live()->required()->inline()->inlineLabel(false)
                    ->extraAttributes(['class' => 'king-media-operation'])
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
            ])
            ->action(function (Action $action, Media $record, array $data, array $arguments, $livewire): void {
                if (! self::readyToSubmit($livewire)) {
                    throw self::operationError($livewire, 'تصویر و محل‌های معتبر را انتخاب کنید؛ حذف فقط پس از رفع تمام وابستگی‌ها مجاز است.');
                }
                if ($data['operation'] !== 'replace' && ! ($arguments['confirmed'] ?? false)) {
                    $livewire->mountTableAction('confirmDeletion');
                    $action->halt();
                }
                try {
                    app(MediaDependencyService::class)->apply($record, filled($data['replacement_media_id'] ?? null) ? (int) $data['replacement_media_id'] : null, $data['dependencies'] ?? [], $data['operation']);
                } catch (MediaInUseException) {
                    throw self::operationError($livewire, 'حذف انجام نشد: هنوز ارجاع باقی مانده است. فقط جایگزینی را انتخاب کنید یا تمام وابستگی‌ها را رفع کنید.');
                } catch (QueryException $exception) {
                    report($exception);
                    throw self::operationError($livewire, 'ارجاع‌ها هم‌زمان تغییر کردند یا ذخیره ناموفق بود؛ تغییرات این عملیات بازگردانده شد. مودال را دوباره باز کنید.');
                } catch (\Throwable $exception) {
                    if ($exception instanceof ValidationException) {
                        throw self::operationError($livewire, collect($exception->errors())->flatten()->all());
                    }
                    if ($exception instanceof HttpExceptionInterface) {
                        throw $exception;
                    }
                    report($exception);
                    throw self::operationError($livewire, 'عملیات ناموفق بود و تغییرات بازگردانده شدند؛ دوباره تلاش کنید.');
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

    private static function replacementOptions(Media $source): array
    {
        $rows = collect(app(MediaDependencyService::class)->modalReport($source))->where('replaceable', true);
        $singleContent = $rows->map(fn ($row) => $row['type'].':'.$row['title'])->unique()->count() === 1;

        return $rows->mapWithKeys(fn ($row) => [$row['key'] => $singleContent ? $row['label'] : $row['title'].' — '.$row['label']])->all();
    }

    private static function operationError($livewire, string|array $message): ValidationException
    {
        return ValidationException::withMessages([$livewire->getMountedTableActionForm()->getStatePath().'.operation' => $message]);
    }

    private static function readyToSubmit($livewire): bool
    {
        $source = $livewire->getMountedTableActionRecord();
        if (! $source) {
            return false;
        }
        $state = $livewire->getMountedTableActionForm()->getRawState();
        $rows = app(MediaDependencyService::class)->modalReport($source);
        $operation = $state['operation'] ?? null;
        if ($operation === 'delete') {
            return $rows === [];
        }
        $replacement = Media::reusableImage($state['replacement_media_id'] ?? null);
        $selected = $state['dependencies'] ?? [];
        $allowed = array_column(array_filter($rows, fn ($row) => $row['replaceable']), 'key');

        return in_array($operation, ['replace', 'replace_delete'], true)
            && $replacement && $replacement->id !== $source->id && MediaResource::canView($replacement)
            && $selected !== [] && array_diff($selected, $allowed) === []
            && ($operation === 'replace' || array_diff(array_column($rows, 'key'), $selected) === []);
    }
}
