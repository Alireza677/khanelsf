<?php

namespace App\Filament\Pages;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Exceptions\BackupOperationException;
use App\Models\Backup;
use App\Services\BackupManager;
use App\Services\BackupUploadService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class Backups extends Page implements HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationGroup = 'نگهداری سیستم';

    protected static ?string $navigationLabel = 'نسخه‌های پشتیبان';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.backups';

    protected static ?string $title = 'نسخه‌های پشتیبان';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() && auth()->user()?->isActive();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fullBackup')
                ->label('ایجاد بکاپ کامل')->icon('heroicon-o-archive-box-arrow-down')->color('primary')
                ->action(fn (BackupManager $manager) => $this->requestBackup($manager, BackupType::Full)),
            Actions\Action::make('uploadBackup')
                ->label('آپلود نسخه پشتیبان')->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\FileUpload::make('archive')
                        ->label('فایل نسخه پشتیبان CMS')
                        ->disk('local')->directory((string) config('backup.incoming_prefix', 'backups/incoming'))
                        ->visibility('private')->acceptedFileTypes(['application/zip', 'application/x-zip-compressed'])
                        ->maxSize($this->effectiveUploadMaxKb())->required()
                        ->helperText('فقط فایل ZIP ساخته‌شده توسط همین CMS پذیرفته می‌شود. حداکثر حجم قابل دریافت این سرور: '.$this->formatBytes($this->effectiveUploadMaxKb() * 1024)),
                ])
                ->action(function (array $data, BackupUploadService $uploads): void {
                    try {
                        $uploads->accept((string) $data['archive'], auth()->user());
                        Notification::make()->success()->title('نسخه پشتیبان با موفقیت بارگذاری شد.')->send();
                    } catch (BackupOperationException $exception) {
                        Notification::make()->danger()->title($exception->getMessage())->send();
                    }
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->visibleBackupsQuery())
            ->poll('5s')
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('تاریخ')->jalaliDateTime()->sortable(),
                Tables\Columns\TextColumn::make('type')->label('نوع')->formatStateUsing(fn (BackupType $state) => $state->label())->badge(),
                Tables\Columns\TextColumn::make('source')->label('منبع')->formatStateUsing(fn ($state) => $state->label()),
                Tables\Columns\TextColumn::make('size_bytes')->label('حجم')->formatStateUsing(fn ($state) => $state ? $this->formatBytes((int) $state) : '—'),
                Tables\Columns\ViewColumn::make('status')->label('وضعیت')
                    ->view('filament.tables.columns.backup-status'),
            ])
            ->actions([
                Tables\Actions\Action::make('details')->label('جزئیات')->icon('heroicon-o-eye')
                    ->modalSubmitAction(false)->modalCancelActionLabel('بستن')
                    ->modalContent(fn (Backup $record) => view('filament.pages.backup-details', ['backup' => $record])),
                Tables\Actions\Action::make('download')->label('دانلود')->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Backup $record) => route('admin.backups.download', $record))
                    ->visible(fn (Backup $record) => $this->fileExists($record)),
                Tables\Actions\Action::make('retry')->label('تلاش مجدد')->icon('heroicon-o-arrow-path')
                    ->visible(fn (Backup $record) => $record->status === BackupStatus::Failed)
                    ->requiresConfirmation()
                    ->action(function (Backup $record, BackupManager $manager): void {
                        abort_unless(auth()->user()?->isAdmin() && auth()->user()?->isActive(), 403);
                        $manager->retry($record);
                        Notification::make()->success()->title('نسخه پشتیبان دوباره در صف ایجاد قرار گرفت.')->send();
                    }),
            ])
            ->emptyStateHeading('هنوز نسخه پشتیبانی ایجاد نشده است.');
    }

    private function visibleBackupsQuery(): Builder
    {
        $pendingIds = Backup::query()
            ->whereIn('status', [BackupStatus::Queued->value, BackupStatus::Creating->value])
            ->latest('created_at')->latest('id')->pluck('id');
        $completedIds = Backup::query()
            ->where('status', BackupStatus::Completed->value)
            ->whereNotNull('local_disk')->whereNotNull('local_path')
            ->latest('finished_at')->latest('id')->limit(3)->pluck('id');
        $failedId = Backup::query()
            ->where('status', BackupStatus::Failed->value)
            ->latest('finished_at')->latest('created_at')->latest('id')->value('id');

        $visibleIds = $pendingIds->merge($completedIds);
        if ($failedId !== null) {
            $visibleIds->push($failedId);
        }

        return Backup::query()
            ->whereIn('id', $visibleIds->unique()->values())
            ->latest('created_at')->latest('id');
    }

    private function requestBackup(BackupManager $manager, BackupType $type): void
    {
        try {
            if ($manager->hasActiveBackup()) {
                throw new BackupOperationException('backup_overlap', 'یک نسخه پشتیبان در حال اجرا یا در صف است.');
            }
            $manager->request($type, auth()->user());
            Notification::make()->success()->title('نسخه پشتیبان در صف ایجاد قرار گرفت.')->send();
        } catch (BackupOperationException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
        }
    }

    private function fileExists(Backup $backup): bool
    {
        return $backup->isAvailable() && Storage::disk($backup->local_disk)->exists($backup->local_path);
    }

    private function effectiveUploadMaxKb(): int
    {
        $configured = max(1, (int) config('backup.upload_max_mb', 2048)) * 1024;
        $phpLimits = array_filter([$this->iniKb('upload_max_filesize'), $this->iniKb('post_max_size')]);

        return $phpLimits ? min($configured, ...$phpLimits) : $configured;
    }

    private function iniKb(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '' || $value === '-1') {
            return 0;
        }
        $number = (float) $value;

        return (int) round($number * match (strtolower(substr($value, -1))) {
            'g' => 1024 * 1024, 'm' => 1024, 'k' => 1, default => 1 / 1024,
        });
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1073741824) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format($bytes / 1073741824, 1).' GB';
    }
}
