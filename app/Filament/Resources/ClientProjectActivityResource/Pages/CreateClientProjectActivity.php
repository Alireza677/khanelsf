<?php

namespace App\Filament\Resources\ClientProjectActivityResource\Pages;

use App\Filament\Resources\ClientProjectActivityResource;
use App\Models\ClientProject;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateClientProjectActivity extends CreateRecord
{
    protected static string $resource = ClientProjectActivityResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (ClientProject::findOrFail($data['client_project_id'])->delivery_mode === 'tasks') {
            throw ValidationException::withMessages(['data.client_project_id' => 'برای این پروژه از ثبت کار / تحویل خروجی استفاده کنید.']);
        }
        $data = ClientProjectActivityResource::applyDurationFormState($data);

        return ClientProjectActivityResource::applyCommercialFormState(ClientProjectActivityResource::applyCycleFormState($data));
    }
}
