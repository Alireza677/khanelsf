<?php

namespace App\Filament\Resources\ServiceOfferingResource\Pages;

use App\Filament\Resources\ServiceOfferingResource;
use App\Filament\Resources\ServiceOfferingVersionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceOffering extends ViewRecord
{
    protected static string $resource = ServiceOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('wizard')->label(fn () => ServiceOfferingResource::wizardLabel($this->record))->url(fn () => ServiceOfferingResource::getUrl('wizard', ['record' => $this->record]))->visible(fn () => ServiceOfferingResource::canUseWizard($this->record)),
            Actions\ActionGroup::make([
                Actions\EditAction::make()->label('تنظیمات بسته')->visible(fn () => $this->record->status !== 'archived' && ServiceOfferingResource::canEdit($this->record)),
                Actions\Action::make('versions')->label('مدیریت پیشرفته نسخه‌ها')->url(ServiceOfferingVersionResource::getUrl())->visible(fn () => ServiceOfferingVersionResource::canViewAny()),
            ])->label('گزینه‌های بیشتر')->color('gray')->button(),
        ];
    }
}
