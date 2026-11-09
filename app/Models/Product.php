<?php

namespace App\Models;

use App\CMS\Navigation\Contracts\ResolvesNavigationUrl;
use App\Models\Concerns\HasFeaturedImage;
use App\Models\Concerns\TracksProductPrice;
use App\Services\ProductPriceFreshness;
use App\Services\ProductPricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Product extends Model implements HasMedia, ResolvesNavigationUrl
{
    protected $attributes = ['price' => '0.00'];

    use HasFactory;
    use HasFeaturedImage;
    use InteractsWithMedia {
        HasFeaturedImage::registerMediaCollections insteadof InteractsWithMedia;
        HasFeaturedImage::registerMediaConversions insteadof InteractsWithMedia;
    }
    use TracksProductPrice;

    protected $fillable = [
        'product_category_id',
        'product_brand_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'price',
        'sale_price',
        'price_validity_mode',
        'price_validity_days',
        'sku',
        'status',
        'published_at',
        'is_featured',
        'sort_order',
        'has_stock',
        'stock_status',
        'seo_title',
        'seo_description',
        'seo_image',
        'robots_index',
        'robots_follow',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'price_changed_at' => 'datetime',
            'price_verified_at' => 'datetime',
            'price_validity_days' => 'integer',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'has_stock' => 'boolean',
            'robots_index' => 'boolean',
            'robots_follow' => 'boolean',
        ];
    }

    public function resolveNavigationUrl(): ?string
    {
        return filled($this->slug)
            ? route('shop.show', $this->slug, absolute: false)
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class)->orderByDesc('id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'product_brand_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ProductTag::class, 'product_product_tag');
    }

    public function attributeAssignments(): HasMany
    {
        return $this->hasMany(ProductAttributeAssignment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProductDocument::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function relatedProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_related_product',
            'product_id',
            'related_product_id',
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('products.id');
    }

    public function relatedBy(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_related_product',
            'related_product_id',
            'product_id',
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('products.id');
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
    }

    public function galleryImages()
    {
        $shared = $this->sharedMedia('gallery');

        return $shared->isNotEmpty() ? $shared : $this->getMedia('gallery');
    }

    public function currentPrice(): float
    {
        return app(ProductPricingService::class)->effectivePrice($this);
    }

    public function hasSalePrice(): bool
    {
        return app(ProductPricingService::class)->salePrice($this) !== null;
    }

    public function isPurchasable(): bool
    {
        return $this->has_stock && $this->stock_status === 'in_stock'
            && $this->status === 'published'
            && (! $this->published_at || $this->published_at->lte(now()))
            && app(ProductPriceFreshness::class)->allowsPurchase($this);
    }
}
