<?php

namespace App\Filament\Resources\ServiceOfferingResource\Pages;

use App\Filament\Resources\ServiceOfferingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceOfferings extends ListRecords
{
    protected static string $resource = ServiceOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('افزودن')];
    }
}
