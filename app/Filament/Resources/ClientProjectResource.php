<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientProjectResource\ContractFields;
use App\Filament\Resources\ClientProjectResource\Pages;
use App\Filament\Resources\ClientProjectResource\RelationManagers\DeliveryCyclesRelationManager;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Models\ClientProject;
use App\Models\Customer;
use App\Models\ServiceOfferingVersion;
use App\Services\ClientProjectCycleUsage;
use App\Services\ClientProjectSchedulePresenter;
use App\Services\DurationFormatter;
use App\Support\PersianDate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientProjectResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = ClientProject::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $slug = 'client-portal/projects';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            ContractFields::creation(),
            Forms\Components\Section::make('اطلاعات پروژه')
                ->schema([
                    Forms\Components\Select::make('customer_id')
                        ->label('مشتری')
                        ->relationship(
                            name: 'customer',
                            titleAttribute: 'display_name',
                            modifyQueryUsing: fn (Builder $query, ?ClientProject $record): Builder => $query
                                ->where(function (Builder $query) use ($record): void {
                                    $query->where('status', Customer::STATUS_ACTIVE);

                                    if ($record?->customer_id) {
                                        $query->orWhere(
                                            $query->getModel()->getQualifiedKeyName(),
                                            $record->customer_id,
                                        );
                                    }
                                }),
                        )
                        ->searchable()
                        ->preload()
                        ->required(),
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان پروژه')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('type')
                        ->label('نوع پروژه')
                        ->maxLength(255),
                    Forms\Components\Select::make('status')
                        ->label('وضعیت')
                        ->options(self::statusOptions())
                        ->default(ClientProject::STATUS_DRAFT)
                        ->required(),
                    Forms\Components\Select::make('schedule_mode')
                        ->label('نوع زمان‌بندی')
                        ->options(self::scheduleModeOptions())
                        ->default(ClientProject::SCHEDULE_RECURRING)
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('progress')
                        ->label('پیشرفت')
                        ->visible(fn (Forms\Get $get, ?ClientProject $record) => self::formDeliveryMode($get, $record) === 'time')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('٪')
                        ->default(0),
                    Forms\Components\Select::make('cycle_anchor_day')
                        ->label('موعد تحویل ماهیانه')
                        ->options(array_combine(range(1, 31), range(1, 31)))
                        ->default(1)
                        ->helperText(fn (?ClientProject $record): ?string => $record && $record->cycle_anchor_day === null
                            ? 'روز ماهانه قبلاً ثبت نشده است؛ برای تعیین آن، روز صحیح قرارداد را انتخاب کنید. خالی ماندن این فیلد زمان‌بندی قبلی را حفظ می‌کند.'
                            : null)
                        ->visible(fn (Forms\Get $get): bool => $get('schedule_mode') === ClientProject::SCHEDULE_RECURRING)
                        ->dehydrated(fn (Forms\Get $get): bool => $get('schedule_mode') === ClientProject::SCHEDULE_RECURRING)
                        ->required(fn (Forms\Get $get, ?ClientProject $record): bool => $get('schedule_mode') === ClientProject::SCHEDULE_RECURRING
                            && (! $record || $record->cycle_anchor_day !== null)),
                    Forms\Components\TextInput::make('monthly_limit_hours')
                        ->visible(fn (Forms\Get $get, ?ClientProject $record) => self::formDeliveryMode($get, $record) !== 'tasks')
                        ->dehydratedWhenHidden()
                        ->label(fn (Forms\Get $get): string => $get('schedule_mode') === ClientProject::SCHEDULE_FIXED_PERIOD
                            ? 'سهم کل پروژه — ساعت'
                            : 'سهم هر دوره — ساعت')
                        ->numeric()->minValue(0)->maxValue(71582788)->default(0)
                        ->live(onBlur: true)
                        ->disabled(fn (Forms\Get $get): bool => (bool) $get('has_unlimited_monthly_hours'))
                        ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                            if ((int) $state > 0) {
                                $set('has_unlimited_monthly_hours', false);
                            }
                        }),
                    Forms\Components\TextInput::make('monthly_limit_remainder_minutes')
                        ->visible(fn (Forms\Get $get, ?ClientProject $record) => self::formDeliveryMode($get, $record) !== 'tasks')
                        ->dehydratedWhenHidden()
                        ->label(fn (Forms\Get $get): string => $get('schedule_mode') === ClientProject::SCHEDULE_FIXED_PERIOD
                            ? 'سهم کل پروژه — دقیقه'
                            : 'سهم هر دوره — دقیقه')
                        ->numeric()->minValue(0)->maxValue(59)->default(0)
                        ->helperText('برای پروژه بدون محدودیت، گزینه زیر را فعال کنید.')
                        ->live(onBlur: true)
                        ->disabled(fn (Forms\Get $get): bool => (bool) $get('has_unlimited_monthly_hours'))
                        ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                            if ((int) $state > 0) {
                                $set('has_unlimited_monthly_hours', false);
                            }
                        }),
                    Forms\Components\Toggle::make('has_unlimited_monthly_hours')
                        ->visible(fn (Forms\Get $get, ?ClientProject $record) => self::formDeliveryMode($get, $record) !== 'tasks')
                        ->dehydratedWhenHidden()
                        ->label('بدون محدودیت زمانی ماهانه')
                        ->default(true)
                        ->live()
                        ->afterStateUpdated(function (Forms\Set $set, mixed $state): void {
                            if ((bool) $state) {
                                $set('monthly_limit_hours', 0);
                                $set('monthly_limit_remainder_minutes', 0);
                            }
                        }),
                    Forms\Components\DatePicker::make('start_date')->jalali()
                        ->label('تاریخ شروع')
                        ->visible(fn (Forms\Get $get): bool => $get('schedule_mode') === ClientProject::SCHEDULE_FIXED_PERIOD)
                        ->dehydratedWhenHidden()
                        ->required(fn (Forms\Get $get): bool => $get('schedule_mode') === ClientProject::SCHEDULE_FIXED_PERIOD),
                    Forms\Components\DatePicker::make('end_date')->jalali()
                        ->label('تاریخ پایان')
                        ->visible(fn (Forms\Get $get): bool => $get('schedule_mode') === ClientProject::SCHEDULE_FIXED_PERIOD)
                        ->dehydratedWhenHidden()
                        ->required(fn (Forms\Get $get): bool => $get('schedule_mode') === ClientProject::SCHEDULE_FIXED_PERIOD)
                        ->after('start_date'),
                    Forms\Components\Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(6)
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('contract_schedule_reason')
                        ->label('دلیل اصلاح زمان‌بندی قرارداد')
                        ->visible(fn (?ClientProject $record, string $operation): bool => $operation === 'edit' && $record !== null && $record->planVersions()->exists())
                        ->helperText('اگر تاریخ، موعد ماهانه یا سهمیه تغییر می‌کند، دلیل را ثبت کنید.')
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'currentCycle' => fn ($query) => app(ClientProjectCycleUsage::class)
                    ->withConsumedAggregate($query),
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان پروژه')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.display_name')->label('مشتری')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->label('نوع')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('deadline')
                    ->label('ددلاین')
                    ->getStateUsing(fn (ClientProject $record): string => app(ClientProjectSchedulePresenter::class)
                        ->deadlineLabel($record, $record->currentCycle)),
                Tables\Columns\TextColumn::make('current_cycle_usage')
                    ->label('مصرف دوره جاری')
                    ->getStateUsing(function (ClientProject $record): string {
                        if ($record->delivery_mode === 'tasks') {
                            return 'تحویل وظیفه‌ای؛ بدون سهم زمانی';
                        }
                        if (! $record->currentCycle) {
                            return 'دوره جاری ندارد';
                        }

                        return app(DurationFormatter::class)->format(
                            app(ClientProjectCycleUsage::class)->consumed($record->currentCycle),
                        );
                    }),
                Tables\Columns\TextColumn::make('current_cycle_overage')
                    ->label('خدمات مازاد')
                    ->getStateUsing(function (ClientProject $record): ?string {
                        if ($record->delivery_mode === 'tasks') {
                            return null;
                        }
                        if (! $record->currentCycle) {
                            return null;
                        }

                        $overage = app(ClientProjectCycleUsage::class)->summary($record->currentCycle)['overage_minutes'];

                        return $overage > 0 ? app(DurationFormatter::class)->format($overage) : null;
                    })
                    ->badge()->color('warning')->placeholder('—'),
                Tables\Columns\TextColumn::make('start_date')->label('شروع')->jalaliDate()->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->label('آخرین تغییر')->jalaliDateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('مشاهده'),
                Tables\Actions\EditAction::make()->label('ویرایش'),
            ])
            ->bulkActions([])
            ->defaultSort('updated_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            ContractFields::history(),
            Infolists\Components\Section::make('اطلاعات پروژه')->schema([
                Infolists\Components\TextEntry::make('title')->label('عنوان پروژه'),
                Infolists\Components\TextEntry::make('customer.display_name')->label('مشتری'),
                Infolists\Components\TextEntry::make('type')->label('نوع پروژه')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
                Infolists\Components\TextEntry::make('schedule_mode')
                    ->label('نوع زمان‌بندی')
                    ->formatStateUsing(fn (string $state): string => self::scheduleModeOptions()[$state] ?? $state),
                Infolists\Components\TextEntry::make('cycle_anchor_day')
                    ->label('موعد تحویل ماهیانه')
                    ->visible(fn (ClientProject $record): bool => $record->isRecurring())
                    ->placeholder('بر اساس زمان‌بندی قبلی'),
                Infolists\Components\TextEntry::make('progress')->label('پیشرفت')->suffix('٪')->visible(fn (ClientProject $record) => $record->delivery_mode === 'time'),
                Infolists\Components\TextEntry::make('monthly_hour_limit_minutes')
                    ->visible(fn (ClientProject $record) => $record->delivery_mode !== 'tasks')
                    ->label(fn (ClientProject $record): string => $record->isFixedPeriod() ? 'سهم کل پروژه' : 'سهم هر دوره')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'بدون محدودیت' : app(DurationFormatter::class)->format($state)),
                Infolists\Components\TextEntry::make('start_date')->label('تاریخ شروع')->jalaliDate()->placeholder('—')
                    ->visible(fn (ClientProject $record): bool => $record->isFixedPeriod()),
                Infolists\Components\TextEntry::make('end_date')->label('تاریخ پایان')->jalaliDate()->placeholder('—')
                    ->visible(fn (ClientProject $record): bool => $record->isFixedPeriod()),
                Infolists\Components\TextEntry::make('description')->label('توضیحات')->placeholder('—')->columnSpanFull(),
                Infolists\Components\RepeatableEntry::make('cycles')->label('دوره‌های تعهد زمانی')->visible(fn (ClientProject $record) => $record->delivery_mode !== 'tasks')->schema([
                    Infolists\Components\TextEntry::make('starts_at')->label('شروع')->formatStateUsing(fn ($state) => PersianDate::date($state)),
                    Infolists\Components\TextEntry::make('ends_at')->label('Deadline')->formatStateUsing(fn ($state) => PersianDate::date($state)),
                    Infolists\Components\TextEntry::make('allocated_minutes')->label('تعهد')->formatStateUsing(fn ($state) => app(DurationFormatter::class)->format($state)),
                    Infolists\Components\TextEntry::make('id')->label('انجام‌شده')->formatStateUsing(fn ($state, $record) => app(DurationFormatter::class)->format(app(ClientProjectCycleUsage::class)->consumed($record))),
                    Infolists\Components\TextEntry::make('remaining_minutes')->label('باقی‌مانده')->getStateUsing(fn ($record) => app(ClientProjectCycleUsage::class)->summary($record)['remaining_minutes'])->formatStateUsing(fn ($state) => app(DurationFormatter::class)->format($state)),
                    Infolists\Components\TextEntry::make('overage_minutes')->label('خدمات مازاد')->getStateUsing(fn ($record) => app(ClientProjectCycleUsage::class)->summary($record)['overage_minutes'])->formatStateUsing(fn ($state) => app(DurationFormatter::class)->format($state))->badge()->color(fn ($state): string => (int) $state > 0 ? 'warning' : 'gray'),
                    Infolists\Components\TextEntry::make('status')->label('وضعیت')->formatStateUsing(fn ($state) => $state->label())->badge(),
                    Infolists\Components\TextEntry::make('invoice.invoice_number')->label('فاکتور')->placeholder('—'),
                ])->columns(4)->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientProjects::route('/'),
            'create' => Pages\CreateClientProject::route('/create'),
            'view' => Pages\ViewClientProject::route('/{record}'),
            'edit' => Pages\EditClientProject::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [DeliveryCyclesRelationManager::class];
    }

    public static function formDeliveryMode(Forms\Get $get, ?ClientProject $record): string
    {
        return $record?->delivery_mode ?? (ServiceOfferingVersion::find($get('offering_version_id'))?->delivery_mode ?? 'time');
    }

    public static function statusOptions(): array
    {
        return [
            ClientProject::STATUS_DRAFT => 'پیش‌نویس',
            ClientProject::STATUS_ACTIVE => 'فعال',
            ClientProject::STATUS_PAUSED => 'متوقف‌شده',
            ClientProject::STATUS_COMPLETED => 'تکمیل‌شده',
            ClientProject::STATUS_CANCELLED => 'لغوشده',
        ];
    }

    public static function scheduleModeOptions(): array
    {
        return [
            ClientProject::SCHEDULE_RECURRING => 'مستمر / دوره‌ای',
            ClientProject::SCHEDULE_FIXED_PERIOD => 'مدت‌دار / یک‌باره',
        ];
    }

    public static function scheduleFormState(array $data): array
    {
        return [
            'schedule_mode' => $data['schedule_mode'] ?? ClientProject::SCHEDULE_RECURRING,
            'cycle_anchor_day' => $data['cycle_anchor_day'] ?? null,
        ];
    }

    public static function allocationFormState(?int $minutes): array
    {
        return [
            'monthly_limit_hours' => $minutes === null ? 0 : intdiv($minutes, 60),
            'monthly_limit_remainder_minutes' => $minutes === null ? 0 : $minutes % 60,
            'has_unlimited_monthly_hours' => $minutes === null,
        ];
    }

    public static function applyAllocationFormState(array $data): array
    {
        if (array_key_exists('cycle_anchor_day', $data)) {
            $data['cycle_anchor_day'] = filled($data['cycle_anchor_day']) ? (int) $data['cycle_anchor_day'] : null;
        }

        if (array_key_exists('progress', $data)) {
            $data['progress'] = (int) ($data['progress'] ?? 0);
        }

        $allocationMinutes = ((int) ($data['monthly_limit_hours'] ?? 0) * 60)
            + (int) ($data['monthly_limit_remainder_minutes'] ?? 0);
        $isUnlimited = (bool) ($data['has_unlimited_monthly_hours'] ?? false);

        $data['monthly_hour_limit_minutes'] = $isUnlimited && $allocationMinutes === 0
            ? null
            : $allocationMinutes;

        unset($data['monthly_limit_hours'], $data['monthly_limit_remainder_minutes'], $data['has_unlimited_monthly_hours']);

        return $data;
    }
}
