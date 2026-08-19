<?php

namespace App\Models;

use App\CMS\Navigation\Contracts\ResolvesNavigationUrl;
use App\Models\Concerns\HasFeaturedImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Page extends Model implements HasMedia, ResolvesNavigationUrl
{
    use HasFactory;
    use HasFeaturedImage;
    use InteractsWithMedia {
        HasFeaturedImage::registerMediaCollections insteadof InteractsWithMedia;
        HasFeaturedImage::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $fillable = [
        'title',
        'slug',
        'content',
        'blocks',
        'template',
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
            'blocks' => 'array',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    public function hasBlocks(): bool
    {
        return collect($this->blocks)->isNotEmpty();
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(Revision::class, 'revisionable');
    }

    public function resolveNavigationUrl(): ?string
    {
        if (blank($this->slug)) {
            return null;
        }

        return match ($this->slug) {
            'home' => route('home', absolute: false),
            'contact' => route('contact.create', absolute: false),
            default => route('pages.show', $this->slug, absolute: false),
        };
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
}
