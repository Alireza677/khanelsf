<?php

namespace App\Filament\Resources\ClientProjectResource;

use App\Enums\ClientProjectDeliveryMode;
use App\Filament\Resources\ClientProjectResource;
use App\Filament\Resources\ServiceOfferingVersionResource;
use App\Models\ClientProject;
use App\Models\ServiceOfferingVersion;
use App\Services\ServiceAgreementData;
use Filament\Forms;
use Filament\Infolists;

final class ContractFields
{
    public static function versionOptions(?int $include = null): array
    {
        return ServiceOfferingVersion::query()->with('offering')
            ->where('status', 'published')
            ->where(fn ($query) => $query->whereHas('offering', fn ($offering) => $offering->where('status', 'active'))
                ->when($include, fn ($query) => $query->orWhere('id', $include)))
            ->get()->mapWithKeys(fn ($version) => [
                $version->id => $version->catalog_snapshot['service']['name'].' / '.$version->catalog_snapshot['offering']['name'].' — نسخه '.$version->version,
            ])->all();
    }

    public static function creation(): Forms\Components\Section
    {
        return Forms\Components\Section::make('بسته و قرارداد مشتری')->schema([
            Forms\Components\Select::make('offering_version_id')->label('نسخه منتشرشده بسته — اختیاری')
                ->options(fn () => self::versionOptions())->searchable()->live()
                ->helperText('خالی بماند: ایجاد پروژه ساعتی با رفتار فعلی.')
                ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                    $version = $state ? ServiceOfferingVersion::find($state) : null;
                    $set('contract_override', false);
                    $set('contract_override_reason', null);
                    if (! $version?->isPublished()) {
                        $set('contract_overrides', []);

                        return;
                    }
                    $definition = ServiceAgreementData::canonical($version->catalog_snapshot);
                    $set('contract_overrides', array_intersect_key($definition, array_flip(ServiceAgreementData::OVERRIDE_FIELDS)));
                    $set('schedule_mode', $definition['schedule_mode']);
                    foreach ($definition['scheduling_defaults'] as $key => $value) {
                        if ($key === 'monthly_hour_limit_minutes') {
                            foreach (ClientProjectResource::allocationFormState((int) $value) as $field => $state) {
                                $set($field, $state);
                            }
                        } else {
                            $set($key, $value);
                        }
                    }
                }),
            Forms\Components\Placeholder::make('delivery_notice')->label('نوع تحویل')
                ->content(function (Forms\Get $get): string {
                    $mode = ServiceOfferingVersion::find($get('offering_version_id'))?->delivery_mode ?? 'time';

                    return ClientProjectDeliveryMode::options()[$mode].($mode !== 'time' ? '؛ تعهد دوره ثبت می‌شود و کارها از بخش دوره‌های تحویل تولید می‌شوند.' : '');
                }),
            Forms\Components\Toggle::make('contract_override')->label('تغییر شرایط برای این مشتری')->default(false)->live()->visible(fn (Forms\Get $get) => filled($get('offering_version_id'))),
            Forms\Components\Section::make('شرایط اختصاصی')->statePath('contract_overrides')
                ->schema(ServiceOfferingVersionResource::agreementFields())->columns(2)
                ->visible(fn (Forms\Get $get) => filled($get('offering_version_id')) && (bool) $get('contract_override'))
                ->dehydrated(fn (Forms\Get $get) => filled($get('offering_version_id')) && (bool) $get('contract_override')),
            Forms\Components\Textarea::make('contract_override_reason')->label('دلیل تغییر قرارداد یا زمان‌بندی')
                ->visible(fn (Forms\Get $get) => filled($get('offering_version_id')))
                ->required(fn (Forms\Get $get) => (bool) $get('contract_override'))
                ->helperText('تغییر پیش‌فرض زمان‌بندی بسته نیز به دلیل نیاز دارد.'),
        ])->visibleOn('create');
    }

    public static function history(): Infolists\Components\Section
    {
        return Infolists\Components\Section::make('تاریخچه قرارداد مشتری')
            ->visible(fn (ClientProject $record) => $record->planVersions()->exists())
            ->schema([
                Infolists\Components\RepeatableEntry::make('planVersions')->label('نسخه‌های قرارداد')->schema([
                    Infolists\Components\TextEntry::make('version')->label('نسخه قرارداد'),
                    Infolists\Components\TextEntry::make('effective_from')->label('تاریخ اثر')->jalaliDate(),
                    Infolists\Components\TextEntry::make('agreement_snapshot.offering.name')->label('بسته در زمان قرارداد'),
                    Infolists\Components\TextEntry::make('agreement_snapshot.offering_version')->label('نسخه بسته'),
                    Infolists\Components\TextEntry::make('agreement_snapshot.delivery_mode')->label('نوع تحویل')->formatStateUsing(fn ($state) => ClientProjectDeliveryMode::options()[$state]),
                    Infolists\Components\TextEntry::make('agreement_snapshot.amount')->label('مبلغ')->placeholder('نیازمند تعیین مبلغ'),
                    Infolists\Components\TextEntry::make('agreement_snapshot.currency_code')->label('واحد پول'),
                    Infolists\Components\TextEntry::make('agreement_snapshot.billing_basis')->label('مبنای مبلغ')->formatStateUsing(fn ($state) => ServiceAgreementData::BILLING_BASES[$state]),
                    Infolists\Components\TextEntry::make('agreement_snapshot.billing_interval')->label('دوره صورتحساب')->formatStateUsing(fn ($state) => ServiceAgreementData::BILLING_INTERVALS[$state]),
                    Infolists\Components\TextEntry::make('agreement_snapshot.contract_terms')->label('شرایط')->columnSpanFull(),
                    ServiceOfferingVersionResource::featuresEntry('agreement_snapshot.features'),
                    Infolists\Components\TextEntry::make('override_reason')->label('دلیل اصلاح')->columnSpanFull(),
                    Infolists\Components\TextEntry::make('creator.name')->label('ثبت‌کننده'),
                    Infolists\Components\RepeatableEntry::make('agreement_snapshot.deliverables')->label('تعهدات ثبت‌شده')->schema([
                        Infolists\Components\TextEntry::make('name')->label('نام'),
                        Infolists\Components\TextEntry::make('description')->label('توضیحات'),
                        Infolists\Components\TextEntry::make('quantity')->label('مقدار'),
                        Infolists\Components\TextEntry::make('unit')->label('واحد'),
                        Infolists\Components\TextEntry::make('approval_required')->label('تأیید مشتری')->formatStateUsing(fn ($state) => $state ? 'بله' : 'خیر'),
                        Infolists\Components\TextEntry::make('revision_allowance')->label('اصلاح مجاز'),
                    ])->columns(3)->columnSpanFull(),
                ])->columns(3),
            ]);
    }
}
