<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClientProject extends ViewRecord
{
    protected static string $resource = ClientProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()->label('ویرایش')];
    }
}
