<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientProjectResource\Pages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Models\ClientProject;
use App\Models\Customer;
use App\Services\DurationFormatter;
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

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
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
                                        $query->orWhereKey($record->customer_id);
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
                    Forms\Components\TextInput::make('progress')
                        ->label('پیشرفت')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('٪')
                        ->default(0)
                        ->required(),
                    Forms\Components\TextInput::make('monthly_limit_hours')
                        ->label('سهم ماهانه — ساعت')
                        ->numeric()->minValue(0)->maxValue(71582788)->default(0),
                    Forms\Components\TextInput::make('monthly_limit_remainder_minutes')
                        ->label('سهم ماهانه — دقیقه')
                        ->numeric()->minValue(0)->maxValue(59)->default(0)
                        ->helperText('برای پروژه بدون محدودیت، گزینه زیر را فعال کنید.'),
                    Forms\Components\Toggle::make('has_unlimited_monthly_hours')
                        ->label('بدون محدودیت زمانی ماهانه')
                        ->default(true),
                    Forms\Components\DatePicker::make('start_date')->jalali()->label('تاریخ شروع'),
                    Forms\Components\DatePicker::make('end_date')->jalali()
                        ->label('تاریخ پایان')
                        ->afterOrEqual('start_date'),
                    Forms\Components\Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(6)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('عنوان پروژه')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.display_name')->label('مشتری')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->label('نوع')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('progress')->label('پیشرفت')->suffix('٪')->sortable(),
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
            Infolists\Components\Section::make('اطلاعات پروژه')->schema([
                Infolists\Components\TextEntry::make('title')->label('عنوان پروژه'),
                Infolists\Components\TextEntry::make('customer.display_name')->label('مشتری'),
                Infolists\Components\TextEntry::make('type')->label('نوع پروژه')->placeholder('—'),
                Infolists\Components\TextEntry::make('status')->label('وضعیت')->badge()->formatStateUsing(fn (string $state): string => self::statusOptions()[$state] ?? $state),
                Infolists\Components\TextEntry::make('progress')->label('پیشرفت')->suffix('٪'),
                Infolists\Components\TextEntry::make('monthly_hour_limit_minutes')
                    ->label('سهم ماهانه')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? 'بدون محدودیت' : app(DurationFormatter::class)->format($state)),
                Infolists\Components\TextEntry::make('start_date')->label('تاریخ شروع')->jalaliDate()->placeholder('—'),
                Infolists\Components\TextEntry::make('end_date')->label('تاریخ پایان')->jalaliDate()->placeholder('—'),
                Infolists\Components\TextEntry::make('description')->label('توضیحات')->placeholder('—')->columnSpanFull(),
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
        $data['monthly_hour_limit_minutes'] = ($data['has_unlimited_monthly_hours'] ?? false)
            ? null
            : ((int) ($data['monthly_limit_hours'] ?? 0) * 60) + (int) ($data['monthly_limit_remainder_minutes'] ?? 0);

        unset($data['monthly_limit_hours'], $data['monthly_limit_remainder_minutes'], $data['has_unlimited_monthly_hours']);

        return $data;
    }
}
