<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class UploadMedia extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $resource = MediaResource::class;

    protected static string $view = 'filament.resources.media-resource.pages.upload-media';

    protected static ?string $title = 'بارگذاری رسانه';

    public ?array $data = [];

    /** @var array<int, string> */
    public array $duplicateFileNames = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('files')
                    ->label('فایل‌ها')
                    ->multiple()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn ($state) => $this->detectDuplicateFileNames($state ?? []))
                    ->disk('public')
                    ->visibility('public')
                    ->storeFiles(false)
                    ->preserveFilenames()
                    ->panelLayout('grid')
                    ->itemPanelAspectRatio(1)
                    ->imagePreviewHeight('150')
                    ->extraAttributes(['class' => 'media-upload-fixed-preview'])
                    ->acceptedFileTypes([
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'image/gif',
                        'image/svg+xml',
                        'video/mp4',
                        'video/webm',
                        'video/quicktime',
                    ])
                    ->maxSize(10240)
                    ->helperText('تصاویر، فایل‌های SVG یا ویدیوها را بارگذاری کنید. فایل‌ها روی دیسک عمومی ذخیره می‌شوند و در کتابخانه رسانه نمایش داده می‌شوند.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $state = $this->form->getState();
            $files = $state['files'] ?? [];
            $user = auth()->user();

            Log::info('Media upload submitted.', [
                'file_count' => is_countable($files) ? count($files) : 0,
                'file_types' => collect($files)
                    ->map(fn ($file): string => is_object($file) ? $file::class : gettype($file))
                    ->values()
                    ->all(),
                'user_id' => $user?->id,
            ]);

            if (! $user) {
                Notification::make()
                    ->title('برای بارگذاری رسانه باید وارد حساب کاربری شده باشید.')
                    ->danger()
                    ->send();

                return;
            }

            $uploadedCount = 0;
            $usedFileNames = Media::query()
                ->pluck('file_name')
                ->mapWithKeys(fn (string $fileName): array => [mb_strtolower($fileName) => true])
                ->all();

            foreach ($files as $file) {
                if ($file instanceof TemporaryUploadedFile) {
                    $originalFileName = $file->getClientOriginalName();
                    $fileName = $this->uniqueFileName($originalFileName, $usedFileNames);

                    $user
                        ->addMedia($file->getRealPath())
                        ->usingName(pathinfo($fileName, PATHINFO_FILENAME))
                        ->usingFileName($fileName)
                        ->toMediaCollection('media_library', 'public');

                    $file->delete();
                    $uploadedCount++;

                    continue;
                }

                if (is_string($file) && Storage::disk('public')->exists($file)) {
                    $originalFileName = basename($file);
                    $fileName = $this->uniqueFileName($originalFileName, $usedFileNames);

                    $user
                        ->addMediaFromDisk($file, 'public')
                        ->usingName(pathinfo($fileName, PATHINFO_FILENAME))
                        ->usingFileName($fileName)
                        ->toMediaCollection('media_library', 'public');

                    Storage::disk('public')->delete($file);
                    $uploadedCount++;
                }
            }

            if ($uploadedCount === 0) {
                Notification::make()
                    ->title('هیچ فایلی بارگذاری نشد')
                    ->body('فرم ارسال شد، اما فایل بارگذاری‌شده‌ای در درخواست پیدا نشد.')
                    ->warning()
                    ->send();

                return;
            }

            Notification::make()
                ->title('رسانه بارگذاری شد')
                ->success()
                ->send();

            $this->redirect(static::getResource()::getUrl('index'));
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('بارگذاری ناموفق بود')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  array<int|string, TemporaryUploadedFile|string>  $files
     */
    private function detectDuplicateFileNames(array $files): void
    {
        $fileNames = collect($files)
            ->map(function (TemporaryUploadedFile|string $file): string {
                return $file instanceof TemporaryUploadedFile
                    ? $file->getClientOriginalName()
                    : basename($file);
            })
            ->filter()
            ->values();

        $selectedCounts = $fileNames
            ->map(fn (string $fileName): string => mb_strtolower($fileName))
            ->countBy();

        $existingFileNames = Media::query()
            ->whereIn('file_name', $fileNames->all())
            ->pluck('file_name')
            ->map(fn (string $fileName): string => mb_strtolower($fileName))
            ->flip();

        $this->duplicateFileNames = $fileNames
            ->filter(fn (string $fileName): bool =>
                $existingFileNames->has(mb_strtolower($fileName)) ||
                $selectedCounts->get(mb_strtolower($fileName), 0) > 1
            )
            ->unique(fn (string $fileName): string => mb_strtolower($fileName))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, bool>  $usedFileNames
     */
    private function uniqueFileName(string $fileName, array &$usedFileNames): string
    {
        $normalizedFileName = mb_strtolower($fileName);

        if (! isset($usedFileNames[$normalizedFileName])) {
            $usedFileNames[$normalizedFileName] = true;

            return $fileName;
        }

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $suffix = 1;

        do {
            $candidate = $name.'-'.$suffix.($extension !== '' ? '.'.$extension : '');
            $suffix++;
        } while (isset($usedFileNames[mb_strtolower($candidate)]));

        $usedFileNames[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('بازگشت به رسانه‌ها')
                ->url(static::getResource()::getUrl('index')),
        ];
    }
}
