<?php

namespace App\Filament\Resources\ServiceOfferingVersionResource\Pages;

use App\Filament\Resources\ServiceOfferingResource;
use App\Filament\Resources\ServiceOfferingVersionResource;
use App\Services\ServiceOfferingPublisher;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceOfferingVersion extends ViewRecord
{
    protected static string $resource = ServiceOfferingVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('ویرایش پیش‌نویس'),
            Actions\Action::make('publish')->label('انتشار نسخه')->requiresConfirmation()
                ->modalDescription('پس از انتشار، قیمت، شرایط و تعهدات این نسخه قفل می‌شوند.')
                ->visible(fn () => ServiceOfferingVersionResource::canEdit($this->record))
                ->action(function (): void {
                    app(ServiceOfferingPublisher::class)->publish($this->record, auth()->user());
                    $this->refreshFormData(['status', 'published_at']);
                }),
            Actions\Action::make('offering')->label('بازگشت به بسته')->url(ServiceOfferingResource::getUrl('view', ['record' => $this->record->service_offering_id])),
        ];
    }
}
