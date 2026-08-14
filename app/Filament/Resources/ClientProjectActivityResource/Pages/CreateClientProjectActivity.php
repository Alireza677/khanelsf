<?php

namespace App\Filament\Resources\ClientProjectActivityResource\Pages;

use App\Filament\Resources\ClientProjectActivityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateClientProjectActivity extends CreateRecord
{
    protected static string $resource = ClientProjectActivityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ClientProjectActivityResource::applyCommercialFormState(
            ClientProjectActivityResource::applyDurationFormState($data),
        );
    }
}
