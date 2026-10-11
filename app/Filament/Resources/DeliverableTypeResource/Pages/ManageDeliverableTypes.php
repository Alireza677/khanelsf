<?php

namespace App\Filament\Resources\DeliverableTypeResource\Pages;

use App\Filament\Resources\DeliverableTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageDeliverableTypes extends ManageRecords
{
    protected static string $resource = DeliverableTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('افزودن')];
    }
}
