<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Concerns\UsesPersianResourceLabels;
use App\Filament\Resources\ProductAttributeResource\Pages;
use App\Filament\Resources\ProductAttributeResource\RelationManagers\ValuesRelationManager;
use App\Filament\Support\ProductCatalogFields;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Services\ModuleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductAttributeResource extends Resource
{
    use UsesPersianResourceLabels;

    protected static ?string $model = ProductAttribute::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 6;

    public static function shouldRegisterNavigation(): bool
    {
        return app(ModuleService::class)->shopEnabled();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('تعریف ویژگی')->description('ابتدا ویژگی را ذخیره کنید، سپس مقادیر مجاز آن را از جدول پایین صفحه اضافه کنید.')->schema([
                ProductCatalogFields::name(), ProductCatalogFields::status(),
                Forms\Components\Toggle::make('is_multiple')->label('انتخاب چند مقدار برای یک محصول')->default(false)
                    ->helperText('مثلاً چند رنگ برای یک محصول؛ این گزینه تنوع قابل خرید ایجاد نمی‌کند.'),
                Forms\Components\Toggle::make('is_filterable')->label('قابل استفاده در فیلتر فروشگاه')->default(false)
                    ->helperText('در این فاز فقط تنظیم ذخیره می‌شود؛ موتور فیلتر در فاز بعد متصل خواهد شد.'),
                Forms\Components\TextInput::make('unit')->label('واحد استاندارد')->maxLength(32)->placeholder('مثلاً mm یا cm')
                    ->helperText('اختیاری؛ برای تمام مقادیر این ویژگی مشترک است و واحد فروش محصول نیست.'),
                ProductCatalogFields::sortOrder(),
                Forms\Components\Select::make('categories')->label('دسته‌های مرتبط')->multiple()->relationship('categories', 'name')
                    ->options(fn () => ProductCategory::treeOptions())->searchable()->preload()->columnSpanFull()
                    ->helperText('اختیاری؛ پیشنهادهای فرم محصول را مشخص می‌کند. تغییر دسته، ویژگی‌های محصول را حذف نمی‌کند.'),
            ])->columns(['default' => 1, 'md' => 2]),
            ProductCatalogFields::identity(stable: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            Tables\Columns\TextColumn::make('name')->label('ویژگی')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('values_count')->label('مقادیر')->counts('values'),
            Tables\Columns\IconColumn::make('is_multiple')->label('چندمقداری')->boolean(),
            Tables\Columns\IconColumn::make('is_filterable')->label('قابل فیلتر')->boolean(),
            Tables\Columns\TextColumn::make('unit')->label('واحد')->placeholder('—'),
            Tables\Columns\TextColumn::make('status')->label('وضعیت')->badge()
                ->formatStateUsing(fn (string $state) => $state === 'active' ? 'فعال' : 'غیرفعال')
                ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('وضعیت')->options(['active' => 'فعال', 'inactive' => 'غیرفعال']),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getRelations(): array
    {
        return [ValuesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListProductAttributes::route('/'), 'create' => Pages\CreateProductAttribute::route('/create'), 'edit' => Pages\EditProductAttribute::route('/{record}/edit')];
    }
}
