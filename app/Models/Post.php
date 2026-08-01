<?php

namespace App\Models;

use App\CMS\Navigation\Contracts\ResolvesNavigationUrl;
use App\Models\Concerns\HasFeaturedImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia, ResolvesNavigationUrl
{
    use HasFactory;
    use HasFeaturedImage;
    use InteractsWithMedia {
        HasFeaturedImage::registerMediaCollections insteadof InteractsWithMedia;
        HasFeaturedImage::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'seo_image',
        'seo_keywords',
        'robots_index',
        'robots_follow',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', 'published')
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function resolveNavigationUrl(): ?string
    {
        return filled($this->slug)
            ? route('blog.show', $this->slug, absolute: false)
            : null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
