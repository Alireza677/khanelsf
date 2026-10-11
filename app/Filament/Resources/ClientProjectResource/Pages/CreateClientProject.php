<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectResource;
use App\Services\ClientProjectCycleReconciler;
use App\Services\CreateClientProjectWithOffering;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateClientProject extends CreateRecord
{
    protected static string $resource = ClientProjectResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ClientProjectResource::applyAllocationFormState($data);
    }

    protected function afterCreate(): void
    {
        if (! $this->record->planVersions()->exists()) {
            app(ClientProjectCycleReconciler::class)->reconcile($this->record, true);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $versionId = $data['offering_version_id'] ?? null;
        $overrides = ($data['contract_override'] ?? false) ? ($data['contract_overrides'] ?? []) : [];
        $reason = $data['contract_override_reason'] ?? null;
        unset($data['offering_version_id'], $data['contract_override'], $data['contract_overrides'], $data['contract_override_reason'], $data['contract_schedule_reason']);
        if (! $versionId) {
            return parent::handleRecordCreation($data);
        }

        return app(CreateClientProjectWithOffering::class)->create($data, (int) $versionId, $overrides, $reason, auth()->user());
    }
}
