<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ViewMedia extends ViewRecord
{
    protected static string $resource = MediaResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\ViewEntry::make('preview')
                ->label('پیش‌نمایش')
                ->view('filament.tables.columns.media-preview')
                ->columnSpanFull(),
            Infolists\Components\TextEntry::make('file_name')
                ->label('نام فایل')
                ->copyable(),
            Infolists\Components\TextEntry::make('mime_type')
                ->label('نوع فایل'),
            Infolists\Components\TextEntry::make('size')
                ->label('حجم')
                ->formatStateUsing(fn (?int $state): string => MediaResource::formatSize($state ?? 0)),
            Infolists\Components\TextEntry::make('disk')
                ->label('دیسک'),
            Infolists\Components\TextEntry::make('collection_name')
                ->label('مجموعه'),
            Infolists\Components\TextEntry::make('created_at')
                ->label('زمان بارگذاری')
                ->jalaliDateTime(),
            Infolists\Components\TextEntry::make('url')
                ->label('لینک')
                ->state(fn (Media $record): string => $record->getUrl())
                ->copyable()
                ->columnSpanFull(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('open')
                ->label('باز کردن')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (Media $record): string => $record->getUrl())
                ->openUrlInNewTab(),
            Actions\DeleteAction::make()
                ->label('حذف')
                ->modalHeading('حذف رسانه')
                ->modalSubmitActionLabel('حذف')
                ->modalCancelActionLabel('انصراف'),
        ];
    }
}
