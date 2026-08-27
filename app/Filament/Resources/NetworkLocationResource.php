<?php

namespace App\Filament\Resources;

use App\Enums\NetworkLocationStatus;
use App\Enums\NetworkLocationType;
use App\Filament\Resources\NetworkLocationResource\Pages;
use App\Models\NetworkLocation;
use App\Services\ModuleService;
use App\Support\IranProvinces;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NetworkLocationResource extends Resource
{
    protected static ?string $model = NetworkLocation::class;
    protected static ?string $navigationGroup = 'فروش و ارتباط با مشتری';
    protected static ?string $navigationLabel = 'شبکه کسب‌وکار';
    protected static ?string $modelLabel = 'مکان شبکه';
    protected static ?string $pluralModelLabel = 'شبکه کسب‌وکار';
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    public static function shouldRegisterNavigation(): bool { return app(ModuleService::class)->businessNetworkEnabled(); }
    public static function canAccess(): bool { return app(ModuleService::class)->businessNetworkEnabled() && parent::canAccess(); }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات اصلی')->schema([
                Forms\Components\Select::make('type')->options(NetworkLocationType::options())->required(),
                Forms\Components\TextInput::make('name')->required()->maxLength(255),
                Forms\Components\TextInput::make('contact_name')->label('نام مسئول')->maxLength(255),
                Forms\Components\TextInput::make('position')->label('سمت')->maxLength(255),
                Forms\Components\Textarea::make('description')->columnSpanFull(),
            ])->columns(2),
            Forms\Components\Section::make('موقعیت')->schema([
                Forms\Components\Select::make('province_code')->label('استان')->options(IranProvinces::options())->searchable()->required(),
                Forms\Components\TextInput::make('city')->label('شهر')->required()->maxLength(255),
                Forms\Components\Textarea::make('address')->label('نشانی')->columnSpanFull(),
                Forms\Components\TextInput::make('latitude')->label('عرض جغرافیایی')->numeric()->minValue(-90)->maxValue(90),
                Forms\Components\TextInput::make('longitude')->label('طول جغرافیایی')->numeric()->minValue(-180)->maxValue(180),
            ])->columns(2),
            Forms\Components\Section::make('اطلاعات تماس')->schema([
                Forms\Components\TextInput::make('mobile')->label('موبایل')->tel()->maxLength(32),
                Forms\Components\TextInput::make('phone')->label('تلفن')->tel()->maxLength(32),
                Forms\Components\TextInput::make('email')->email()->maxLength(255),
            ])->columns(3),
            Forms\Components\Section::make('نمایش')->schema([
                Forms\Components\Select::make('status')->options(NetworkLocationStatus::options())->default('active')->required(),
                Forms\Components\TextInput::make('sort_order')->numeric()->minValue(0)->default(0)->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('type')->formatStateUsing(fn ($state) => ($state instanceof NetworkLocationType ? $state : NetworkLocationType::tryFrom((string)$state))?->label())->badge(),
            Tables\Columns\TextColumn::make('province_code')->label('استان')->formatStateUsing(fn ($state) => IranProvinces::name((string)$state))->sortable(),
            Tables\Columns\TextColumn::make('city')->label('شهر')->searchable(),
            Tables\Columns\TextColumn::make('status')->formatStateUsing(fn ($state) => ($state instanceof NetworkLocationStatus ? $state : NetworkLocationStatus::tryFrom((string)$state))?->label())->badge(),
            Tables\Columns\TextColumn::make('sort_order')->sortable(),
            Tables\Columns\TextColumn::make('updated_at')->jalaliDateTime()->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('type')->options(NetworkLocationType::options()),
            Tables\Filters\SelectFilter::make('province_code')->label('استان')->options(IranProvinces::options())->searchable(),
            Tables\Filters\SelectFilter::make('status')->options(NetworkLocationStatus::options()),
        ])->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array { return ['index'=>Pages\ListNetworkLocations::route('/'),'create'=>Pages\CreateNetworkLocation::route('/create'),'edit'=>Pages\EditNetworkLocation::route('/{record}/edit')]; }
}
