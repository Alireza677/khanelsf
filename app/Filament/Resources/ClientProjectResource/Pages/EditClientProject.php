<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectResource;
use App\Models\ClientProject;
use App\Services\ClientProjectAgreementManager;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditClientProject extends EditRecord
{
    protected static string $resource = ClientProjectResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            ...ClientProjectResource::allocationFormState($data['monthly_hour_limit_minutes']),
            ...ClientProjectResource::scheduleFormState($data),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Preserve a legacy null; never discard a day explicitly selected in the form.
        return ClientProjectResource::applyAllocationFormState($data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ClientProject $record */
        $reason = $data['contract_schedule_reason'] ?? null;
        unset($data['contract_schedule_reason'], $data['offering_version_id'], $data['contract_override'], $data['contract_overrides'], $data['contract_override_reason']);

        return app(ClientProjectAgreementManager::class)->updateProject($record, $data, $reason, auth()->user());
    }

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()->label('مشاهده')];
    }
}
