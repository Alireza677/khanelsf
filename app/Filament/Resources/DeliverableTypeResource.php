<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\DeliverableTypeResource\Pages\ManageDeliverableTypes;
use App\Models\DeliverableType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DeliverableTypeResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = DeliverableType::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('نام')->required()->maxLength(255),
            Forms\Components\TextInput::make('unit')->label('واحد')->default('count')->required()->maxLength(100)->helperText('مانند عدد، جلسه یا واحد سفارشی قرارداد.'),
            Forms\Components\Toggle::make('active')->label('فعال')->default(true),
            Forms\Components\Textarea::make('description')->label('توضیحات')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('code')->label('کد')->searchable(),
            Tables\Columns\TextColumn::make('name')->label('نام')->searchable(),
            Tables\Columns\TextColumn::make('unit')->label('واحد'),
            Tables\Columns\IconColumn::make('active')->label('فعال')->boolean(),
        ])->actions([Tables\Actions\EditAction::make()])->bulkActions([]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliverableTypes::route('/')];
    }
}
