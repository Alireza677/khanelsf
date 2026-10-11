<?php

namespace App\Filament\Resources\ServiceOfferingResource\Pages;

use App\Filament\Resources\ServiceOfferingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceOffering extends CreateRecord
{
    protected static string $resource = ServiceOfferingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, 'service_id' => request()->integer('service') ?: null];
    }
}
