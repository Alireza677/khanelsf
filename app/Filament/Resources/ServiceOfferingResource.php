<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ServiceOfferingResource\Pages;
use App\Filament\Resources\ServiceOfferingResource\RelationManagers\VersionsRelationManager;
use App\Models\ServiceOffering;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ServiceOfferingResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = ServiceOffering::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('service_id')->label('خدمت')->relationship('service', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('name')->label('نام بسته')->required()->maxLength(255),
            Forms\Components\TextInput::make('code')->label('کد بسته')->required()->maxLength(255)->unique(ignoreRecord: true),
            Forms\Components\Select::make('status')->label('وضعیت')->options(self::statuses())->default('draft')->required(),
            Forms\Components\TextInput::make('sort_order')->label('ترتیب نمایش')->integer()->minValue(0)->default(0)->required(),
            Forms\Components\Textarea::make('description')->label('توضیحات')->columnSpanFull(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('بسته')->searchable(),
            Tables\Columns\TextColumn::make('service.name')->label('خدمت')->searchable(),
            Tables\Columns\TextColumn::make('code')->label('کد')->searchable(),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()->formatStateUsing(fn ($state) => self::statuses()[$state]),
            Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
        ])->actions([Tables\Actions\ViewAction::make(), Tables\Actions\EditAction::make()])->bulkActions([])->defaultSort('sort_order');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\TextEntry::make('name')->label('بسته'),
            Infolists\Components\TextEntry::make('service.name')->label('خدمت'),
            Infolists\Components\TextEntry::make('code')->label('کد'),
            Infolists\Components\TextEntry::make('description')->label('توضیحات'),
        ]);
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
        return ['index' => Pages\ListServiceOfferings::route('/'), 'create' => Pages\CreateServiceOffering::route('/create'), 'view' => Pages\ViewServiceOffering::route('/{record}'), 'edit' => Pages\EditServiceOffering::route('/{record}/edit')];
    }
}
