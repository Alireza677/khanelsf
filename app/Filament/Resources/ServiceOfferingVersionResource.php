<?php

namespace App\Filament\Resources;

use App\Enums\ClientProjectDeliveryMode;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ServiceOfferingVersionResource\Pages;
use App\Models\DeliverableType;
use App\Models\ServiceOfferingVersion;
use App\Services\ServiceAgreementData;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceOfferingVersionResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = ServiceOfferingVersion::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('شرایط نسخه')->schema([
                Forms\Components\Select::make('delivery_mode')->label('نوع تحویل')->options(ClientProjectDeliveryMode::options())->required(),
                Forms\Components\Select::make('schedule_mode')->label('زمان‌بندی')->options(ClientProjectResource::scheduleModeOptions())->required(),
                ...self::agreementFields(true),
            ])->columns(2),
        ]);
    }

    public static function agreementFields(bool $relationship = false): array
    {
        $deliverables = Forms\Components\Repeater::make('deliverables')->label('تعهدات تحویل')->schema([
            Forms\Components\Select::make('deliverable_type_id')->label('نوع تحویل')->options(fn () => DeliverableType::query()->pluck('name', 'id'))->searchable()->required(),
            Forms\Components\TextInput::make('name')->label('نام تعهد')->required()->maxLength(255),
            Forms\Components\Textarea::make('description')->label('توضیحات'),
            Forms\Components\TextInput::make('quantity')->label('مقدار')->required()->default('1')->regex('/^\d{1,14}(?:\.\d{1,4})?$/')->inputMode('decimal'),
            Forms\Components\TextInput::make('unit')->label('واحد')->required()->default('count')->maxLength(100),
            Forms\Components\Toggle::make('approval_required')->label('نیازمند تأیید مشتری')->default(false),
            Forms\Components\TextInput::make('revision_allowance')->label('تعداد اصلاح مجاز')->integer()->minValue(0)->maxValue(4294967295)->default(0)->required(),
            Forms\Components\TextInput::make('scheduling_rules.due_day_offset')->label('موعد: روز پس از شروع دوره')->integer()->minValue(0)->maxValue(3660),
        ])->defaultItems(0)->columns(2)->columnSpanFull()->itemLabel(fn (array $state) => $state['name'] ?? 'تعهد جدید');
        if ($relationship) {
            $deliverables->relationship('deliverables')->orderColumn('sort_order');
        }

        return [
            Forms\Components\Select::make('billing_basis')->label('مبنای مبلغ')->options(ServiceAgreementData::BILLING_BASES)->required()->default('package'),
            Forms\Components\Select::make('billing_interval')->label('دوره صورتحساب')->options(ServiceAgreementData::BILLING_INTERVALS)->required()->default('monthly'),
            Forms\Components\TextInput::make('amount')->label('مبلغ')->regex('/^\d{1,14}(?:\.\d{1,4})?$/')->inputMode('decimal')->helperText('خالی: نیازمند تعیین مبلغ؛ صفر: بدون هزینه.'),
            Forms\Components\TextInput::make('currency_code')->label('کد واحد پول')->required()->default('IRT')->regex('/^[A-Z]{3}$/')->maxLength(3),
            Forms\Components\Textarea::make('contract_terms')->label('شرایط قرارداد')->columnSpanFull(),
            self::featuresField(),
            Forms\Components\TextInput::make('scheduling_defaults.cycle_anchor_day')->label('روز ماهانه پیش‌فرض')->integer()->minValue(1)->maxValue(31),
            Forms\Components\TextInput::make('scheduling_defaults.monthly_hour_limit_minutes')->label('سهمیه پیش‌فرض — دقیقه')->integer()->minValue(0)->maxValue(4294967295),
            Forms\Components\DatePicker::make('scheduling_defaults.start_date')->label('شروع پیش‌فرض')->jalali(),
            Forms\Components\DatePicker::make('scheduling_defaults.end_date')->label('پایان پیش‌فرض')->jalali()->after('scheduling_defaults.start_date'),
            $deliverables,
        ];
    }

    public static function featuresField(): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('features')->label('ویژگی‌ها و شرایط پکیج')
            ->helperText('اطلاعات توصیفی پکیج؛ مستقل از تعهدات مقداری و بدون ایجاد کار اجرایی.')
            ->schema([
                Forms\Components\TextInput::make('title')->label('عنوان ویژگی')->required()->maxLength(255),
                Forms\Components\Select::make('type')->label('نوع مقدار')->options(ServiceAgreementData::FEATURE_TYPES)->default('text')->required()->live()
                    ->afterStateUpdated(function (Forms\Set $set, ?string $state): void {
                        $set('value', $state === 'boolean' ? false : null);
                        $set('unit', null);
                    }),
                Forms\Components\Group::make()->schema(fn (Forms\Get $get) => [match ($get('type')) {
                    'boolean' => Forms\Components\Toggle::make('value')->label('دارد')->onIcon('heroicon-o-check')->offIcon('heroicon-o-x-mark')->default(false)->helperText('خاموش: ندارد؛ روشن: دارد.'),
                    'number' => Forms\Components\TextInput::make('value')->label('مقدار عددی')->required()->regex(ServiceAgreementData::FEATURE_NUMBER)->inputMode('decimal')->helperText('عدد با حداکثر ۴ رقم اعشار؛ مانند ۶۰.'),
                    default => Forms\Components\Textarea::make('value')->label('مقدار متنی')->required()->maxLength(2000)->placeholder('مانند ۳ تا ۴ ساعت'),
                }]),
                Forms\Components\TextInput::make('unit')->label('واحد نمایشی — اختیاری')->maxLength(100)
                    ->visible(fn (Forms\Get $get) => $get('type') === 'number'),
            ])->defaultItems(0)->maxItems(100)->columns(2)->columnSpanFull()->extraAttributes(['dir' => 'rtl'])
            ->addActionLabel('افزودن ویژگی')->itemLabel(fn (array $state) => $state['title'] ?? 'ویژگی جدید');
    }

    public static function featuresEntry(string $path = 'features'): Infolists\Components\RepeatableEntry
    {
        return Infolists\Components\RepeatableEntry::make($path)->label('ویژگی‌ها و شرایط پکیج')->schema([
            Infolists\Components\TextEntry::make('title')->label('عنوان'),
            Infolists\Components\TextEntry::make('type')->label('نوع')->formatStateUsing(fn ($state) => ServiceAgreementData::FEATURE_TYPES[$state] ?? $state),
            Infolists\Components\TextEntry::make('value')->label('مقدار')->formatStateUsing(fn ($state, Infolists\Components\TextEntry $component) => data_get($component->getContainer()->getState(), 'type') === 'boolean' ? ($state ? 'دارد' : 'ندارد') : $state),
            Infolists\Components\TextEntry::make('unit')->label('واحد نمایشی')->placeholder('—')->visible(fn (Infolists\Components\TextEntry $component) => data_get($component->getContainer()->getState(), 'type') === 'number'),
        ])->columns(4)->columnSpanFull();
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('version')->label('نسخه'),
            Infolists\Components\TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn ($state) => $state === 'published' ? 'منتشرشده و قفل‌شده' : 'پیش‌نویس'),
            Infolists\Components\TextEntry::make('delivery_mode')->label('نوع تحویل')->formatStateUsing(fn ($state) => ClientProjectDeliveryMode::options()[$state]),
            Infolists\Components\TextEntry::make('schedule_mode')->label('زمان‌بندی')->formatStateUsing(fn ($state) => ClientProjectResource::scheduleModeOptions()[$state]),
            Infolists\Components\TextEntry::make('amount')->label('مبلغ')->placeholder('نیازمند تعیین مبلغ'),
            Infolists\Components\TextEntry::make('currency_code')->label('واحد پول'),
            Infolists\Components\TextEntry::make('billing_basis')->label('مبنای مبلغ')->formatStateUsing(fn ($state) => ServiceAgreementData::BILLING_BASES[$state]),
            Infolists\Components\TextEntry::make('billing_interval')->label('دوره صورتحساب')->formatStateUsing(fn ($state) => ServiceAgreementData::BILLING_INTERVALS[$state]),
            Infolists\Components\TextEntry::make('contract_terms')->label('شرایط قرارداد')->columnSpanFull(),
            self::featuresEntry(),
            Infolists\Components\TextEntry::make('published_at')->label('انتشار')->jalaliDateTime(),
            Infolists\Components\RepeatableEntry::make('deliverables')->label('تعهدات')->schema([
                Infolists\Components\TextEntry::make('name')->label('نام'),
                Infolists\Components\TextEntry::make('description')->label('توضیحات'),
                Infolists\Components\TextEntry::make('quantity')->label('مقدار'),
                Infolists\Components\TextEntry::make('unit')->label('واحد'),
                Infolists\Components\TextEntry::make('approval_required')->label('تأیید مشتری')->formatStateUsing(fn ($state) => $state ? 'بله' : 'خیر'),
                Infolists\Components\TextEntry::make('revision_allowance')->label('اصلاح مجاز'),
                Infolists\Components\TextEntry::make('scheduling_rules.due_day_offset')->label('فاصله موعد — روز'),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('offering.name')->label('بسته'),
            Tables\Columns\TextColumn::make('version')->label('نسخه'),
            Tables\Columns\TextColumn::make('status')->label('وضعیت'),
        ])->actions([Tables\Actions\ViewAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListServiceOfferingVersions::route('/'), 'view' => Pages\ViewServiceOfferingVersion::route('/{record}'), 'edit' => Pages\EditServiceOfferingVersion::route('/{record}/edit')];
    }
}
