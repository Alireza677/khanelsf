<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ProductBrandResource\Pages;
use App\Filament\Support\ProductCatalogFields;
use App\Models\ProductBrand;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductBrandResource extends Resource
{
    use UsesMediaLibraryImages;
    use UsesPersianResourceLabels;

    protected static ?string $model = ProductBrand::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->shopEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('اطلاعات برند')->description('برند، مستقل از فروشنده یا تولیدکننده ثبت می‌شود.')->schema([
                ProductCatalogFields::name(), ProductCatalogFields::status(),
                Forms\Components\Textarea::make('description')->label('توضیحات')->rows(3)->columnSpanFull(),
                Forms\Components\ViewField::make('featured_media_id')->label('نشان برند')
                    ->view('filament.forms.components.media-library-picker')
                    ->viewData(fn (): array => ['images' => static::mediaLibraryImageItems()])->dehydrated(false)
                    ->afterStateHydrated(fn (Forms\Set $set, ?ProductBrand $record) => $set('featured_media_id', static::mediaLibraryFeaturedState($record)))
                    ->helperText('اختیاری؛ از کتابخانه رسانه فعلی انتخاب کنید.'),
                ProductCatalogFields::sortOrder(),
            ])->columns(['default' => 1, 'md' => 2]),
            ProductCatalogFields::identity(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('name')->label('برند')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('products_count')->label('محصولات')->counts('products'),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                ->formatStateUsing(fn (string $state) => $state === 'active' ? 'فعال' : 'غیرفعال')
                ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
            Tables\Columns\TextColumn::make('sort_order')->label('ترتیب')->sortable(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('وضعیت')->options(['active' => 'فعال', 'inactive' => 'غیرفعال']),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProductBrands::route('/'), 'create' => Pages\CreateProductBrand::route('/create'), 'edit' => Pages\EditProductBrand::route('/{record}/edit')];
    }
}
