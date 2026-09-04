<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectResource;
use App\Services\ClientProjectCycleReconciler;
use Filament\Resources\Pages\CreateRecord;

class CreateClientProject extends CreateRecord
{
    protected static string $resource = ClientProjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ClientProjectResource::applyAllocationFormState($data);
    }

    protected function afterCreate(): void
    {
        app(ClientProjectCycleReconciler::class)->reconcile($this->record, true);
    }
}
