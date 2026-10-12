<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ServiceOfferingResource\Pages;
use App\Filament\Resources\ServiceOfferingResource\RelationManagers\VersionsRelationManager;
use App\Models\ServiceOffering;
use App\Models\ServiceOfferingVersion;
use App\Services\ServiceAgreementData;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceOfferingResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = ServiceOffering::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            ...self::metadataFields(),
            Forms\Components\Select::make('status')->label('وضعیت')->options(self::statuses())->default('draft')->required(),
        ])->columns(2);
    }

    public static function metadataFields(): array
    {
        return [
            Forms\Components\Select::make('service_id')->label('خدمت')->relationship('service', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('name')->label('نام بسته')->required()->maxLength(255),
            Forms\Components\TextInput::make('sort_order')->label('ترتیب نمایش')->integer()->minValue(0)->default(0)->required(),
            Forms\Components\Textarea::make('description')->label('توضیحات')->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('بسته')->searchable(),
            Tables\Columns\TextColumn::make('service.name')->label('خدمت')->searchable(),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn ($state) => self::statuses()[$state]),
            Tables\Columns\TextColumn::make('version_summary')->label('وضعیت انتشار')->state(fn (ServiceOffering $record) => self::versionSummary($record)),
            Tables\Columns\TextColumn::make('current_price')->label('قیمت جاری')->state(fn (ServiceOffering $record) => self::priceSummary($record)),
            Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
        ])->actions([
            Tables\Actions\Action::make('wizard')->label(fn (ServiceOffering $record) => self::wizardLabel($record))->url(fn ($record) => self::getUrl('wizard', ['record' => $record]))->visible(fn ($record) => self::canUseWizard($record)),
            Tables\Actions\ViewAction::make()->label('مشاهده بسته'),
        ])->bulkActions([])->defaultSort('sort_order');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('name')->label('بسته'),
            Infolists\Components\TextEntry::make('service.name')->label('خدمت'),
            Infolists\Components\TextEntry::make('status')->label('وضعیت بسته')->badge()->formatStateUsing(fn ($state) => self::statuses()[$state]),
            Infolists\Components\TextEntry::make('version_summary')->label('وضعیت انتشار')->state(fn (ServiceOffering $record) => self::versionSummary($record)),
            Infolists\Components\TextEntry::make('description')->label('توضیحات'),
            Infolists\Components\Section::make('شرایط جاری بسته')->schema(function (ServiceOffering $record): array {
                $version = self::publishedVersion($record) ?? self::pendingDraft($record);
                if (! $version) {
                    return [Infolists\Components\TextEntry::make('complete_help')->hiddenLabel()->state('اطلاعات نسخه را با «ادامه تکمیل بسته» وارد کنید.')];
                }
                $entries = ServiceOfferingVersionResource::definitionEntries();
                foreach ($entries as $entry) {
                    $entry->state($entry->getName() === 'deliverables' ? $version->deliverables()->get()->toArray() : $version->getAttribute($entry->getName()));
                }

                return $entries;
            })->columns(2)->columnSpanFull(),
        ])->columns(2);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['service', 'versions']);
    }

    public static function publishedVersion(ServiceOffering $offering): ?ServiceOfferingVersion
    {
        return $offering->versions->firstWhere('status', 'published');
    }

    public static function pendingDraft(ServiceOffering $offering): ?ServiceOfferingVersion
    {
        $published = self::publishedVersion($offering);

        return $offering->versions->first(fn ($version) => $version->status === 'draft' && $version->version > ($published?->version ?? 0));
    }

    public static function wizardLabel(ServiceOffering $offering): string
    {
        if (self::publishedVersion($offering)) {
            return self::pendingDraft($offering) ? 'ادامه ویرایش نسخه جدید' : 'ایجاد نسخه جدید';
        }

        return self::pendingDraft($offering) ? 'ویرایش و انتشار' : 'ادامه تکمیل بسته';
    }

    public static function canUseWizard(ServiceOffering $offering): bool
    {
        return $offering->status !== 'archived' && self::canEdit($offering);
    }

    public static function versionSummary(ServiceOffering $offering): string
    {
        $published = self::publishedVersion($offering);
        $draft = self::pendingDraft($offering);

        return $published ? 'منتشرشده — نسخه '.$published->version.($draft ? '؛ پیش‌نویس جدید در حال تکمیل' : '') : ($draft ? 'پیش‌نویس در حال تکمیل' : 'نیازمند تکمیل بسته');
    }

    public static function priceSummary(ServiceOffering $offering): string
    {
        $version = self::publishedVersion($offering) ?? self::pendingDraft($offering);

        return $version?->amount !== null ? $version->amount.' '.$version->currency_code.' / '.ServiceAgreementData::BILLING_INTERVALS[$version->billing_interval] : 'نیازمند تعیین مبلغ';
    }

    public static function statuses(): array
    {
        return ['draft' => 'پیش‌نویس', 'active' => 'فعال', 'archived' => 'بایگانی‌شده'];
    }

    public static function getRelations(): array
    {
        return [VersionsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListServiceOfferings::route('/'), 'create' => Pages\CreateServiceOffering::route('/create'), 'view' => Pages\ViewServiceOffering::route('/{record}'), 'edit' => Pages\EditServiceOffering::route('/{record}/edit'), 'wizard' => Pages\ManageServiceOffering::route('/{record}/wizard')];
    }
}
