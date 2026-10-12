<?php

namespace App\Filament\Resources\ServiceOfferingResource\Pages;

use App\Filament\Resources\DeliverableTypeResource;
use App\Filament\Resources\ServiceOfferingResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceOfferings extends ListRecords
{
    protected static string $resource = ServiceOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deliverableTypes')->label('مدیریت انواع تحویل')->color('gray')->url(DeliverableTypeResource::getUrl())->visible(fn () => DeliverableTypeResource::canViewAny()),
            CreateAction::make()->label('ایجاد بسته خدمات'),
        ];
    }
}
