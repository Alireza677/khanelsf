<?php

namespace App\Models;

use App\Models\Concerns\HasCatalogIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ProductAttributeValue extends Model
{
    use HasCatalogIdentity;

    protected $fillable = ['product_attribute_id', 'name', 'slug', 'status', 'sort_order'];

    protected $attributes = ['status' => 'active', 'sort_order' => 0];

    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            ProductAttribute::query()->lockForUpdate()->findOrFail($this->product_attribute_id);
            if ($this->exists && $this->isDirty('product_attribute_id')) {
                throw ValidationException::withMessages(['product_attribute_id' => 'مقدار را نمی‌توان به ویژگی دیگری منتقل کرد.']);
            }
            Validator::make($this->getAttributes(), ['status' => ['in:active,inactive'], 'sort_order' => ['integer', 'min:0']])->validate();

            return parent::save($options);
        });
    }

    protected static function booted(): void
    {
        static::deleting(function (self $value): void {
            if ($value->assignments()->exists()) {
                throw ValidationException::withMessages(['value' => 'این مقدار در محصولات استفاده شده است؛ آن را غیرفعال کنید.']);
            }
        });
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'product_attribute_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductAttributeAssignment::class);
    }
}
