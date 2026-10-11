<?php

namespace App\Filament\Resources\ClientProjectWorkItemResource\Pages;

use App\Filament\Resources\ClientProjectWorkItemResource;
use App\Models\ClientProjectCycle;
use App\Services\ClientProjectWorkItemManager;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkItems extends ManageRecords
{
    protected static string $resource = ClientProjectWorkItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('کار اضافی جدید')->using(fn (array $data) => app(ClientProjectWorkItemManager::class)->createExtra(ClientProjectCycle::findOrFail($data['client_project_cycle_id']), $data, auth()->user()))];
    }
}
