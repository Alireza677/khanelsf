<?php

namespace App\Models\Concerns;

use App\Models\ProductAttributeValue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait HasCatalogIdentity
{
    public static function catalogNameKey(string $name): string
    {
        $name = Str::lower(trim(preg_replace('/\s+/u', ' ', strtr($name, [
            'ي' => 'ی', 'ك' => 'ک', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.',
        ]))));

        // Choices are labels, not inferred numbers: codes such as 001 and 1 may differ.
        return $name;
    }

    protected static function bootHasCatalogIdentity(): void
    {
        static::saving(function ($record): void {
            $record->name = trim((string) $record->name);
            $record->name_key = static::catalogNameKey($record->name);
            if (blank($record->slug)) {
                $record->slug = Str::slug($record->name) ?: 'item-'.Str::lower(Str::random(12));
            }
            $scope = fn ($query) => $record instanceof ProductAttributeValue
                ? $query->where('product_attribute_id', $record->product_attribute_id) : $query;
            $validator = Validator::make($record->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'name_key' => ['required', 'max:255', Rule::unique($record->getTable(), 'name_key')->where($scope)->ignore($record->getKey())],
                'slug' => ['required', 'string', 'max:255', Rule::unique($record->getTable(), 'slug')->where($scope)->ignore($record->getKey())],
                'status' => ['sometimes', 'in:active,inactive'],
                'sort_order' => ['sometimes', 'integer', 'min:0'],
            ], ['name_key.unique' => 'این نام یا مقدار قبلاً ثبت شده است.', 'slug.unique' => 'این نامک قبلاً ثبت شده است.']);
            if ($validator->fails()) {
                throw ValidationException::withMessages(collect($validator->errors()->messages())
                    ->mapWithKeys(fn ($messages, $field) => [$field === 'name_key' ? 'name' : $field => $messages])->all());
            }
        });
    }
}
