<?php

namespace App\Filament\Support;

use Closure;
use Filament\Forms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class ProductCatalogFields
{
    public static function name(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('name')->label('نام')->required()->maxLength(255)
            ->rule(fn (?Model $record, Forms\Components\TextInput $component) => self::uniqueNameRule($record, $component->getModel()))
            ->live(onBlur: true)
            ->afterStateUpdated(fn (Forms\Get $get, Forms\Set $set, ?string $state) => blank($get('slug'))
                ? $set('slug', Str::slug($state ?? '')) : null);
    }

    public static function uniqueNameRule(?Model $record, string $model, ?int $attributeId = null): Closure
    {
        return function (string $field, mixed $value, Closure $fail) use ($record, $model, $attributeId): void {
            $query = $model::query()->where('name_key', $model::catalogNameKey((string) $value))
                ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->id))
                ->when($attributeId, fn ($query) => $query->where('product_attribute_id', $attributeId));
            if ($query->exists()) {
                $fail('این نام یا مقدار قبلاً ثبت شده است.');
            }
        };
    }

    public static function identity(bool $stable = false): Forms\Components\Section
    {
        return Forms\Components\Section::make('تنظیمات پیشرفته')->collapsed()->collapsible()->schema([
            Forms\Components\TextInput::make('slug')->label('شناسه / نامک')->maxLength(255)
                ->unique(ignoreRecord: true)->disabled(fn (string $operation) => $stable && $operation === 'edit')
                ->helperText($stable ? 'خودکار ساخته می‌شود و پس از ایجاد ویژگی ثابت می‌ماند.' : 'در صورت خالی بودن، از نام ساخته می‌شود.'),
        ])->columnSpanFull();
    }

    public static function status(): Forms\Components\Select
    {
        return Forms\Components\Select::make('status')->label('وضعیت')->required()
            ->options(['active' => 'فعال', 'inactive' => 'غیرفعال'])->default('active');
    }

    public static function sortOrder(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('sort_order')->label('ترتیب نمایش')->integer()->minValue(0)->required()->default(0);
    }
}
