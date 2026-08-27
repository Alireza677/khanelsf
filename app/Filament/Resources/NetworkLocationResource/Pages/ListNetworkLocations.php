<?php

namespace App\Filament\Resources\NetworkLocationResource\Pages;

use App\Filament\Resources\NetworkLocationResource;
use App\Services\ModuleService;
use App\Services\NetworkLocationImportService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;

class ListNetworkLocations extends ListRecords
{
    protected static string $resource = NetworkLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('import')
                ->label('ورود از اکسل')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => app(ModuleService::class)->businessNetworkEnabled() && NetworkLocationResource::canCreate())
                ->modalHeading('ورود گروهی شبکه کسب‌وکار')
                ->modalDescription('فایل باید دارای ستون‌های type، name، province و city باشد. فرمت‌های CSV و XLSX پشتیبانی می‌شوند.')
                ->modalSubmitActionLabel('شروع ورود')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('فایل اکسل یا CSV')
                        ->acceptedFileTypes(['text/csv','text/plain','application/csv','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)
                        ->storeFiles(false)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    abort_unless(app(ModuleService::class)->businessNetworkEnabled() && NetworkLocationResource::canCreate(), 403);
                    $file = $data['file'] ?? null;
                    abort_unless($file instanceof UploadedFile, 422);
                    try {
                        $result = app(NetworkLocationImportService::class)->import($file->getRealPath());
                    } finally {
                        $file->delete();
                    }

                    if (! $result->successful()) {
                        $shown = $result->errorMessages();
                        $remaining = count($result->errors) - count($shown);
                        $body = implode("\n", array_map(fn (string $message): string => "• {$message}", $shown));
                        if ($remaining > 0) $body .= "\n• و {$remaining} خطای دیگر";
                        Notification::make()->danger()->title('ورود اطلاعات انجام نشد')->body($body)->persistent()->send();
                        return;
                    }

                    $title = $result->created > 0 ? 'ورود اطلاعات با موفقیت انجام شد' : 'مورد جدیدی برای ورود وجود نداشت';
                    Notification::make()->success()->title($title)->body("{$result->created} مورد ایجاد شد.\n{$result->duplicates} مورد تکراری نادیده گرفته شد.\n{$result->ignoredBlankRows} ردیف خالی نادیده گرفته شد.")->send();
                }),
        ];
    }
}
