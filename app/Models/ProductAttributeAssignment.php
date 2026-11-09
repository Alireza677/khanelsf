<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class ProductAttributeAssignment extends Model
{
    protected $fillable = ['product_id', 'product_attribute_id', 'product_attribute_value_id', 'sort_order'];

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            Product::query()->lockForUpdate()->findOrFail($this->product_id);
            $attribute = ProductAttribute::query()->lockForUpdate()->findOrFail($this->product_attribute_id);
            $value = ProductAttributeValue::query()->whereBelongsTo($attribute, 'attribute')->find($this->product_attribute_value_id);
            $unchanged = $this->exists && ! $this->isDirty(['product_id', 'product_attribute_id', 'product_attribute_value_id']);
            if (! $value || (! $unchanged && ($attribute->status !== 'active' || $value->status !== 'active'))) {
                throw ValidationException::withMessages(['catalog_attributes' => 'مقدار باید متعلق به همین ویژگی و برای انتخاب تازه فعال باشد.']);
            }
            $others = static::query()->where('product_id', $this->product_id)->where('product_attribute_id', $attribute->id)
                ->when($this->exists, fn ($query) => $query->whereKeyNot($this->id));
            if ((! $attribute->is_multiple && (clone $others)->exists()) || $others->where('product_attribute_value_id', $value->id)->exists()) {
                throw ValidationException::withMessages(['catalog_attributes' => 'مقدار تکراری است یا این ویژگی فقط یک مقدار می‌پذیرد.']);
            }

            return parent::save($options);
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    public function value(): BelongsTo
    {
        return $this->belongsTo(ProductAttributeValue::class, 'product_attribute_value_id');
    }
}
