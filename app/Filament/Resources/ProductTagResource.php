<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ProductTagResource\Pages;
use App\Filament\Support\ProductCatalogFields;
use App\Models\ProductTag;
use App\Services\ModuleService;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductTagResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = ProductTag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->shopEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([ProductCatalogFields::name(), ProductCatalogFields::identity()]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('name')->columns([
            Tables\Columns\TextColumn::make('name')->label('برچسب')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('products_count')->label('محصولات')->counts('products'),
            Tables\Columns\TextColumn::make('slug')->label('نامک')->toggleable(isToggledHiddenByDefault: true),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProductTags::route('/'), 'create' => Pages\CreateProductTag::route('/create'), 'edit' => Pages\EditProductTag::route('/{record}/edit')];
    }
}
