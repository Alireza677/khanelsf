<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectResource;
use App\Models\ClientProject;
use App\Services\ClientProjectCycleReconciler;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ClientProject $record */
        return app(ClientProjectCycleReconciler::class)->updateProject($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()->label('مشاهده')];
    }
}
