<?php

namespace App\Models;

use App\CMS\Navigation\Contracts\ResolvesNavigationUrl;
use App\Models\Concerns\HasFeaturedImage;
use App\Services\ProjectServiceResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Project extends Model implements HasMedia, ResolvesNavigationUrl
{
    use HasFactory;
    use HasFeaturedImage;
    use InteractsWithMedia {
        HasFeaturedImage::registerMediaCollections insteadof InteractsWithMedia;
        HasFeaturedImage::registerMediaConversions insteadof InteractsWithMedia;
    }

    protected $fillable = [
        'project_category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'client_name',
        'location',
        'industry',
        'project_type',
        'project_date',
        'project_started_at',
        'project_completed_at',
        'challenge',
        'solution',
        'results_summary',
        'client_quote',
        'services',
        'attributes',
        'external_url',
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
            'project_date' => 'date',
            'project_started_at' => 'date',
            'project_completed_at' => 'date',
            'services' => 'array',
            'attributes' => 'array',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    public function resolveNavigationUrl(): ?string
    {
        return filled($this->slug)
            ? route('projects.show', $this->slug, absolute: false)
            : null;
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

    public function hasCaseStudyData(): bool
    {
        return collect([
            $this->project_started_at,
            $this->project_completed_at,
            $this->industry,
            $this->project_type,
            $this->challenge,
            $this->solution,
            $this->results_summary,
            $this->client_quote,
        ])->contains(fn (mixed $value): bool => filled($value));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProjectCategory::class, 'project_category_id');
    }

    public function relatedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)
            ->withTimestamps()
            ->orderBy('services.sort_order')
            ->orderBy('services.name');
    }

    public function serviceNames(): Collection
    {
        return app(ProjectServiceResolver::class)->names($this);
    }

    public function serviceItems(): Collection
    {
        return app(ProjectServiceResolver::class)->items($this);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(ProjectMetric::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(ProjectVideo::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function discoveryTerms(): BelongsToMany
    {
        return $this->belongsToMany(ProjectDiscoveryTerm::class, 'project_discovery_term_project')
            ->with('vocabulary')
            ->orderBy('project_discovery_terms.sort_order')
            ->orderBy('project_discovery_terms.name');
    }

    public function registerMediaCollections(): void
    {
        $this->registerFeaturedImageMediaCollection();

        $this
            ->addMediaCollection('gallery')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->registerFeaturedImageMediaConversions($media);

        $this
            ->addMediaConversion('card')
            ->width(720)
            ->height(480)
            ->nonQueued();
    }

    public function galleryImages()
    {
        return $this->getMedia('gallery');
    }

    public function coverImage(): ?Media
    {
        return $this->featuredImage() ?: $this->galleryImages()->first();
    }

    public function coverImageUrl(?string $conversionName = 'card'): ?string
    {
        $media = $this->coverImage();

        if (! $media) {
            return null;
        }

        if (filled($conversionName) && $media->hasGeneratedConversion($conversionName)) {
            return $media->getUrl($conversionName);
        }

        return $media->getUrl();
    }
}
