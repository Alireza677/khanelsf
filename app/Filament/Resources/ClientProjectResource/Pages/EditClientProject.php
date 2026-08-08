<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClientProject extends EditRecord
{
    protected static string $resource = ClientProjectResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...ClientProjectResource::allocationFormState($data['monthly_hour_limit_minutes'])];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ClientProjectResource::applyAllocationFormState($data);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()->label('مشاهده')];
    }
}
