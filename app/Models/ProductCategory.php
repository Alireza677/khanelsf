<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'status',
        'sort_order',
        'price_validity_days',
        'seo_title',
        'seo_description',
        'seo_image',
        'robots_index',
        'robots_follow',
    ];

    protected function casts(): array
    {
        return [
            'price_validity_days' => 'integer',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttribute::class, 'product_attribute_category');
    }

    public function save(array $options = [])
    {
        if (! $this->isDirty('parent_id') || $this->parent_id === null) {
            return parent::save($options);
        }

        return $this->getConnection()->transaction(function () use ($options) {
            // Serialize hierarchy edits, including concurrent A -> B / B -> A changes.
            // This runs only for an explicit parent change, never for price/category reads.
            $parents = static::query()->orderBy('id')->lockForUpdate()->pluck('parent_id', 'id')->all();
            $next = (int) $this->parent_id;
            $seen = $this->exists ? [(int) $this->id => true] : [];
            while ($next) {
                if (isset($seen[$next])) {
                    throw ValidationException::withMessages(['parent_id' => 'انتخاب این والد باعث ایجاد چرخه در دسته‌بندی می‌شود.']);
                }
                if (! array_key_exists($next, $parents)) {
                    throw ValidationException::withMessages(['parent_id' => 'دسته والد معتبر نیست.']);
                }
                $seen[$next] = true;
                $next = (int) $parents[$next];
            }

            return parent::save($options);
        }, 3);
    }

    /** Readable full paths; also excludes descendants from the parent selector. */
    public static function treeOptions(?int $excludeId = null): array
    {
        $categories = static::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'parent_id', 'name'])->keyBy('id');

        return $categories->mapWithKeys(function (self $category) use ($categories, $excludeId): array {
            $path = [];
            $seen = [];
            $current = $category;
            while ($current) {
                if ((int) $current->id === $excludeId || isset($seen[$current->id])) {
                    return [];
                }
                $seen[$current->id] = true;
                array_unshift($path, $current->name);
                $current = $categories->get($current->parent_id);
            }

            return [$category->id => implode(' / ', $path)];
        })->all();
    }
}
