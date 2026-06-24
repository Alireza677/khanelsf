<?php

namespace App\Filament\Resources\MediaResource\Pages;

use App\Filament\Resources\MediaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMedia extends ListRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('upload')
                ->label('بارگذاری رسانه')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(static::getResource()::getUrl('upload')),
        ];
    }
}
