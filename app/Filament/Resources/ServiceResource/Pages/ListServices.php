<?php

namespace App\Filament\Resources\ServiceResource\Pages;

use App\Filament\Resources\ServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServices extends ListRecords
{
    protected static string $resource = ServiceResource::class;

    public function getTitle(): string
    {
        return 'خدمات';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('ایجاد خدمت'),
        ];
    }
}
