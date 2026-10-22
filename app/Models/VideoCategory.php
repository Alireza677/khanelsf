<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class VideoCategory extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order'];

    protected $attributes = ['sort_order' => 0];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if (! $category->exists && blank($category->slug)) {
                $category->slug = Str::slug($category->name) ?: Str::lower((string) Str::ulid());
            }
            Validator::make($category->getAttributes(), [
                'name' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', Rule::unique('video_categories', 'slug')->ignore($category->getKey())],
                'sort_order' => ['required', 'integer', 'min:0', 'max:2147483647'],
            ])->validate();
        });
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class);
    }
}
