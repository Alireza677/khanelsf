<?php

namespace App\Filament\Resources\ClientProjectActivityResource\Pages;

use App\Filament\Actions\ActivityCreationWizardAction;
use App\Filament\Resources\ClientProjectActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientProjectActivities extends ListRecords
{
    protected static string $resource = ClientProjectActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActivityCreationWizardAction::make(),
            Actions\CreateAction::make()->label('فرم کامل')->color('gray'),
        ];
    }
}
