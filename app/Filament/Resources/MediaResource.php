<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\MediaResource\Pages;
use App\Filament\Resources\MediaResource\Pages\ListMedia;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = Media::class;

    protected static ?string $navigationGroup = 'رسانه';

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'کتابخانه رسانه';

    protected static ?int $navigationSort = 4;

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->contentGrid(fn (ListMedia $livewire): ?array => $livewire->mediaView === 'grid'
                ? [
                    'default' => 5,
                ]
                : null)
            ->columns([
                Tables\Columns\ViewColumn::make('grid_preview')
                    ->label('رسانه')
                    ->view('filament.tables.columns.media-grid-card')
                    ->visible(fn (ListMedia $livewire): bool => $livewire->mediaView === 'grid'),
                Tables\Columns\ViewColumn::make('preview')
                    ->label('پیش‌نمایش')
                    ->view('filament.tables.columns.media-preview')
                    ->visible(fn (ListMedia $livewire): bool => $livewire->mediaView === 'list'),
                Tables\Columns\TextColumn::make('file_name')
                    ->label('فایل')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->visible(fn (ListMedia $livewire): bool => $livewire->mediaView === 'list'),
                Tables\Columns\TextColumn::make('mime_type')
                    ->label('نوع فایل')
                    ->badge()
                    ->searchable()
                    ->sortable()
                    ->visible(fn (ListMedia $livewire): bool => $livewire->mediaView === 'list'),
                Tables\Columns\TextColumn::make('size')
                    ->label('حجم')
                    ->formatStateUsing(fn (?int $state): string => static::formatSize($state ?? 0))
                    ->sortable()
                    ->visible(fn (ListMedia $livewire): bool => $livewire->mediaView === 'list'),
                Tables\Columns\TextColumn::make('disk')
                    ->label('دیسک')
                    ->badge()
                    ->sortable()
                    ->visible(fn (ListMedia $livewire): bool => $livewire->mediaView === 'list'),
                Tables\Columns\TextColumn::make('collection_name')
                    ->label('مجموعه')
                    ->badge()
                    ->sortable()
                    ->visible(fn (ListMedia $livewire): bool => $livewire->mediaView === 'list'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('زمان بارگذاری')
                    ->dateTime()
                    ->sortable()
                    ->visible(fn (ListMedia $livewire): bool => $livewire->mediaView === 'list'),
            ])
            ->filters([
                Tables\Filters\Filter::make('images')
                    ->label('تصاویر')
                    ->query(fn (Builder $query): Builder => $query->where('mime_type', 'like', 'image/%')),
                Tables\Filters\Filter::make('videos')
                    ->label('ویدیوها')
                    ->query(fn (Builder $query): Builder => $query->where('mime_type', 'like', 'video/%')),
                Tables\Filters\SelectFilter::make('disk')
                    ->label('دیسک')
                    ->options(fn (): array => Media::query()
                        ->distinct()
                        ->pluck('disk', 'disk')
                        ->all()),
                Tables\Filters\SelectFilter::make('collection_name')
                    ->label('مجموعه')
                    ->options(fn (): array => Media::query()
                        ->distinct()
                        ->pluck('collection_name', 'collection_name')
                        ->all()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('مشاهده'),
                Tables\Actions\Action::make('open')
                    ->label('باز کردن')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Media $record): string => $record->getUrl())
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('copyUrl')
                    ->label('کپی لینک')
                    ->icon('heroicon-o-link')
                    ->modalHeading('لینک رسانه')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن')
                    ->modalContent(fn (Media $record) => view('filament.media.copy-url', [
                        'url' => $record->getUrl(),
                    ])),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->modalHeading('حذف رسانه')
                    ->modalSubmitActionLabel('حذف')
                    ->modalCancelActionLabel('انصراف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('حذف گروهی')
                        ->modalHeading('حذف رسانه‌های انتخاب‌شده')
                        ->modalSubmitActionLabel('حذف')
                        ->modalCancelActionLabel('انصراف'),
                ]),
            ])
            ->emptyStateHeading('هنوز رسانه‌ای بارگذاری نشده است')
            ->emptyStateDescription('تصاویر و فایل‌ها را اینجا بارگذاری کنید و سپس در برگه‌ها، نوشته‌ها، پروژه‌ها، فیلدهای سئو و بلوک‌های محتوا استفاده کنید.')
            ->emptyStateIcon('heroicon-o-photo');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedia::route('/'),
            'upload' => Pages\UploadMedia::route('/upload'),
            'view' => Pages\ViewMedia::route('/{record}'),
        ];
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / 1024 / 1024, 1).' MB';
    }
}
