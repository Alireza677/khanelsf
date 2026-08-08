<?php

namespace App\Models;

use App\Models\Concerns\HasFeaturedImage;
use App\Services\ExternalVideoResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Gallery extends Model implements HasMedia
{
    use HasFactory;
    use HasFeaturedImage;
    use InteractsWithMedia {
        HasFeaturedImage::registerMediaCollections insteadof InteractsWithMedia;
        HasFeaturedImage::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $fillable = [
        'gallery_category_id',
        'project_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'type',
        'video_url',
        'thumbnail_url',
        'status',
        'published_at',
        'is_featured',
        'sort_order',
        'seo_title',
        'seo_description',
        'seo_image',
        'robots_index',
        'robots_follow',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GalleryCategory::class, 'gallery_category_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeWithPublicCategory(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->whereNull('gallery_category_id')
                ->orWhereHas('category', fn (Builder $query) => $query->active());
        });
    }

    public function registerMediaCollections(): void
    {
        $this->registerFeaturedImageMediaCollection();

        $this
            ->addMediaCollection('images')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerFeaturedImageMediaConversions($media);
    }

    public function images()
    {
        return $this->getMedia('images');
    }

    public function cardImageUrl(?string $conversion = 'thumb'): ?string
    {
        return $this->thumbnail_url
            ?: $this->featuredImageUrl($conversion)
            ?: $this->images()->first()?->getUrl();
    }

    public function videoEmbedUrl(): ?string
    {
        if (blank($this->video_url)) {
            return null;
        }

        return app(ExternalVideoResolver::class)->resolve((string) $this->video_url)['embed_url'];
    }
}
