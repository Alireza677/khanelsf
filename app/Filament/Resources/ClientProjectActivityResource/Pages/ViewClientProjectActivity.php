<?php

namespace App\Filament\Resources\ClientProjectActivityResource\Pages;

use App\Filament\Resources\ClientProjectActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewClientProjectActivity extends ViewRecord
{
    protected static string $resource = ClientProjectActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()->label('ویرایش')];
    }
}
