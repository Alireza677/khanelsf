<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientProjects extends ListRecords
{
    protected static string $resource = ClientProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('پروژه جدید')];
    }
}
