<?php

namespace App\Filament\Resources\ServiceOfferingVersionResource\Pages;

use App\Filament\Resources\ServiceOfferingResource;
use App\Filament\Resources\ServiceOfferingVersionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditServiceOfferingVersion extends EditRecord
{
    protected static string $resource = ServiceOfferingVersionResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getRedirectUrl(): string
    {
        return ServiceOfferingVersionResource::getUrl('view', ['record' => $this->record]);
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('offering')->label('بازگشت به بسته')->url(ServiceOfferingResource::getUrl('view', ['record' => $this->record->service_offering_id]))];
    }
}
