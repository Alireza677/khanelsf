<?php

namespace App\Filament\Support;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Services\ProductCatalogService;
use Filament\Forms;
use Illuminate\Validation\ValidationException;

final class ProductCatalogEditor
{
    public static function field(): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('catalog_attributes')->label('ویژگی‌های عمومی')
            ->helperText('ویژگی و مقادیر مجاز را انتخاب کنید. این اطلاعات قیمت یا موجودی جداگانه ایجاد نمی‌کنند. مقادیر غیرفعال قبلی حفظ می‌شوند.')
            ->schema([
                Forms\Components\Select::make('attribute_id')->label('ویژگی')->required()->searchable()->preload()->live()
                    ->options(fn (Forms\Get $get, ?Product $record) => self::attributeOptions($record, $get('../../product_category_id')))
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                    ->afterStateUpdated(fn (Forms\Set $set) => $set('value_ids', [])),
                Forms\Components\Select::make('value_ids')->label('مقادیر')->multiple()->required()->minItems(1)->searchable()->preload()
                    ->disabled(fn (Forms\Get $get) => blank($get('attribute_id')))
                    ->maxItems(fn (Forms\Get $get) => self::attribute($get('attribute_id'))?->is_multiple ? 100 : 1)
                    ->helperText(fn (Forms\Get $get) => ($attribute = self::attribute($get('attribute_id')))
                        ? (($attribute->is_multiple ? 'یک یا چند مقدار' : 'فقط یک مقدار').($attribute->unit ? '؛ واحد: '.$attribute->unit : ''))
                        : 'ابتدا ویژگی را انتخاب کنید.')
                    ->options(function (Forms\Get $get, ?Product $record): array {
                        $attribute = self::attribute($get('attribute_id'));
                        if (! $attribute) {
                            return [];
                        }
                        $saved = $record?->attributeAssignments()->where('product_attribute_id', $attribute->id)->pluck('product_attribute_value_id')->all() ?? [];

                        return $attribute->values()
                            ->when($attribute->status === 'active', fn ($query) => $query->where(fn ($query) => $query->where('status', 'active')->orWhereIn('id', $saved)),
                                fn ($query) => $query->whereIn('id', $saved))
                            ->get()->mapWithKeys(fn ($value) => [$value->id => $value->name.($value->status !== 'active' ? ' (غیرفعال؛ مقدار قبلی)' : '')])->all();
                    }),
            ])->columns(['default' => 1, 'md' => 2])->defaultItems(0)->maxItems(100)->reorderable()->collapsible()
            ->itemLabel(fn (array $state) => self::attribute($state['attribute_id'] ?? null)?->name ?? 'ویژگی جدید')
            ->addActionLabel('افزودن ویژگی')->dehydrated(false)
            ->afterStateHydrated(fn (Forms\Set $set, ?Product $record) => $set('catalog_attributes', $record ? app(ProductCatalogService::class)->formState($record) : []))
            ->columnSpanFull();
    }

    public static function sync(Product $product, array $state): void
    {
        // A missing field in an older caller never means "delete all assignments".
        if (! array_key_exists('catalog_attributes', $state)) {
            return;
        }
        try {
            app(ProductCatalogService::class)->syncAttributes($product, $state['catalog_attributes'] ?? []);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all());
        }
    }

    private static function attribute(mixed $id): ?ProductAttribute
    {
        return is_scalar($id) && ctype_digit((string) $id) ? ProductAttribute::find($id) : null;
    }

    private static function attributeOptions(?Product $record, mixed $categoryId): array
    {
        $saved = $record?->attributeAssignments()->pluck('product_attribute_id')->all() ?? [];
        $categoryId = is_scalar($categoryId) ? (int) $categoryId : null;

        return ProductAttribute::query()->where('data_type', 'choice')
            ->where(fn ($query) => $query->where('status', 'active')->orWhereIn('id', $saved))
            ->with('categories:id')->orderBy('sort_order')->orderBy('name')->get()
            ->mapWithKeys(fn ($attribute) => [$attribute->id => $attribute->name
                .($attribute->categories->contains('id', $categoryId) ? ' (مرتبط با دسته)' : '')
                .($attribute->status !== 'active' ? ' (غیرفعال؛ ویژگی قبلی)' : '')])->all();
    }
}
