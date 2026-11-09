<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductAttribute extends Model
{
    use HasCatalogIdentity;

    protected $fillable = ['name', 'slug', 'unit', 'is_multiple', 'is_filterable', 'status', 'sort_order'];

    protected $attributes = ['data_type' => 'choice', 'status' => 'active', 'is_multiple' => false, 'is_filterable' => false, 'sort_order' => 0];

    protected function casts(): array
    {
        return ['is_multiple' => 'boolean', 'is_filterable' => 'boolean', 'sort_order' => 'integer'];
    }

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            $current = $this->exists ? static::query()->lockForUpdate()->findOrFail($this->id) : null;
            Validator::make($this->getAttributes(), [
                'data_type' => ['in:choice'], 'status' => ['in:active,inactive'],
                'unit' => ['nullable', 'string', 'max:32'], 'sort_order' => ['integer', 'min:0'],
            ])->validate();
            if ($current && $this->slug !== $current->slug) {
                throw ValidationException::withMessages(['slug' => 'شناسه ویژگی پس از ایجاد ثابت است.']);
            }
            if ($current && $this->unit !== $current->unit && $this->assignments()->exists()) {
                throw ValidationException::withMessages(['unit' => 'واحد ویژگی استفاده‌شده قابل تغییر نیست؛ ویژگی تازه‌ای تعریف کنید.']);
            }
            if ($current && ! $this->is_multiple && $this->assignments()->select('product_id')->groupBy('product_id')->havingRaw('COUNT(*) > 1')->exists()) {
                throw ValidationException::withMessages(['is_multiple' => 'ابتدا مقادیر چندتایی محصولات را به یک مقدار کاهش دهید.']);
            }

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::deleting(function (self $attribute): void {
            if ($attribute->assignments()->exists()) {
                throw ValidationException::withMessages(['attribute' => 'این ویژگی در محصولات استفاده شده است؛ آن را غیرفعال کنید.']);
            }
        });
    }

    public function values(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class)->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductAttributeAssignment::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'product_attribute_category');
    }
}
