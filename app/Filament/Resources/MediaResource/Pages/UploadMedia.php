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
use Throwable;

class UploadMedia extends Page implements HasForms
{
    use Forms\Concerns\InteractsWithForms;

    protected static string $resource = MediaResource::class;

    protected static string $view = 'filament.resources.media-resource.pages.upload-media';

    protected static ?string $title = 'بارگذاری رسانه';

    public ?array $data = [];

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

            foreach ($files as $file) {
                if ($file instanceof TemporaryUploadedFile) {
                    $fileName = $file->getClientOriginalName();

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
                    $fileName = basename($file);

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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('بازگشت به رسانه‌ها')
                ->url(static::getResource()::getUrl('index')),
        ];
    }
}
