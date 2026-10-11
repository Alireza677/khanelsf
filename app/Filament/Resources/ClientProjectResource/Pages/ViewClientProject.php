<?php

namespace App\Filament\Resources\ClientProjectResource\Pages;

use App\Filament\Resources\ClientProjectResource;
use App\Filament\Resources\ClientProjectResource\ContractFields;
use App\Filament\Resources\ServiceOfferingVersionResource;
use App\Models\ServiceOfferingVersion;
use App\Services\ClientProjectAgreementManager;
use App\Services\ServiceAgreementData;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewClientProject extends ViewRecord
{
    protected static string $resource = ClientProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('ویرایش'),
            Actions\Action::make('reviseAgreement')->label('ثبت نسخه جدید قرارداد')
                ->visible(fn () => ClientProjectResource::canEdit($this->record) && $this->record->planVersions()->exists())
                ->fillForm(function (): array {
                    $latest = $this->record->planVersions()->orderByDesc('version')->firstOrFail();

                    return [
                        'offering_version_id' => $latest->service_offering_version_id,
                        'effective_from' => $this->record->delivery_mode === 'time' ? today()->toDateString() : ($this->record->cycles()->latest('ends_at')->first()?->ends_at->toDateString() ?? today()->addDay()->toDateString()),
                        'overrides' => $latest->agreement_snapshot,
                    ];
                })
                ->form([
                    Forms\Components\Select::make('offering_version_id')->label('نسخه بسته')->required()->live()
                        ->options(fn () => ContractFields::versionOptions($this->record->planVersions()->orderByDesc('version')->value('service_offering_version_id')))
                        ->afterStateUpdated(fn (Forms\Set $set, $state) => $set('overrides', ServiceOfferingVersion::find($state)?->catalog_snapshot ?? [])),
                    Forms\Components\DatePicker::make('effective_from')->label('تاریخ اثر')->jalali()->required()->minDate(today())
                        ->helperText('برای تحویل وظیفه‌ای یا ترکیبی، شروع دوره آینده‌ای را انتخاب کنید که هنوز تعهد ثبت‌شده ندارد.'),
                    Forms\Components\Section::make('شرایط توافق‌شده')->statePath('overrides')->schema(ServiceOfferingVersionResource::agreementFields())->columns(2),
                    Forms\Components\Textarea::make('contract_override_reason')->label('دلیل اصلاح')->required(),
                ])
                ->action(function (array $data): void {
                    $overrides = array_intersect_key($data['overrides'], array_flip(ServiceAgreementData::OVERRIDE_FIELDS));
                    app(ClientProjectAgreementManager::class)->append($this->record, ServiceOfferingVersion::findOrFail($data['offering_version_id']), $overrides, $data['contract_override_reason'], $data['effective_from'], auth()->user());
                    $this->record->unsetRelation('planVersions');
                }),
        ];
    }
}
