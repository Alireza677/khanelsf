<?php

namespace App\Filament\Resources\ClientProjectActivityResource\Pages;

use App\Filament\Resources\ClientProjectActivityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClientProjectActivity extends EditRecord
{
    protected static string $resource = ClientProjectActivityResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...ClientProjectActivityResource::durationFormState((int) $data['duration_minutes'])];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ClientProjectActivityResource::applyCommercialFormState(
            ClientProjectActivityResource::applyDurationFormState($data),
            $this->record,
        );
    }

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()->label('مشاهده')];
    }
}
