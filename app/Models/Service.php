<?php

namespace App\Models;

use App\CMS\Navigation\Contracts\ResolvesNavigationUrl;
use App\Enums\ServicePricingMode;
use App\Enums\ServiceUnit;
use App\Models\Concerns\HasFeaturedImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Service extends Model implements HasMedia, ResolvesNavigationUrl
{
    use HasFeaturedImage;
    use InteractsWithMedia {
        HasFeaturedImage::registerMediaCollections insteadof InteractsWithMedia;
        HasFeaturedImage::registerMediaConversions insteadof InteractsWithMedia;
    }

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $attributes = [
        'available_for_activities' => false,
    ];

    protected $fillable = [
        'name',
        'slug',
        'excerpt',
        'overview',
        'benefits',
        'process',
        'deliverables',
        'status',
        'published_at',
        'sort_order',
        'seo_title',
        'seo_description',
        'icon',
        'available_for_activities',
        'pricing_mode',
        'unit',
        'custom_unit_label',
        'default_unit_price',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'benefits' => 'array',
            'process' => 'array',
            'deliverables' => 'array',
            'published_at' => 'datetime',
            'available_for_activities' => 'boolean',
            'pricing_mode' => ServicePricingMode::class,
            'unit' => ServiceUnit::class,
            'default_unit_price' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $service): void {
            $mode = $service->pricing_mode instanceof ServicePricingMode
                ? $service->pricing_mode
                : ServicePricingMode::tryFrom((string) $service->pricing_mode);
            $unit = $service->unit instanceof ServiceUnit
                ? $service->unit
                : ServiceUnit::tryFrom((string) $service->unit);
            $errors = [];

            if ($service->pricing_mode !== null && ! $mode) {
                $errors['pricing_mode'] = 'Pricing mode is invalid.';
            }
            if ($service->unit !== null && ! $unit) {
                $errors['unit'] = 'Service unit is invalid.';
            }
            if ($mode && ! $unit) {
                $errors['unit'] = 'A unit is required when pricing mode is selected.';
            }
            if ($mode === ServicePricingMode::Hourly && $unit !== ServiceUnit::Hour) {
                $errors['unit'] = 'Hourly pricing requires the hour unit.';
            }
            if ($mode === ServicePricingMode::Fixed && $unit !== ServiceUnit::Fixed) {
                $errors['unit'] = 'Fixed pricing requires the fixed unit.';
            }
            if ($mode === ServicePricingMode::PerUnit && in_array($unit, [ServiceUnit::Hour, ServiceUnit::Fixed], true)) {
                $errors['unit'] = 'Per-unit pricing requires a delivery unit.';
            }
            if ($unit === ServiceUnit::Custom && blank($service->custom_unit_label)) {
                $errors['custom_unit_label'] = 'A custom unit label is required.';
            }
            if ($unit !== ServiceUnit::Custom && filled($service->custom_unit_label)) {
                $errors['custom_unit_label'] = 'A custom unit label is only valid for a custom unit.';
            }
            if ($service->default_unit_price !== null && bccomp((string) $service->default_unit_price, '0', 4) < 0) {
                $errors['default_unit_price'] = 'Unit price cannot be negative.';
            }
            if (filled($service->currency_code) && ! preg_match('/^[A-Z]{3}$/', (string) $service->currency_code)) {
                $errors['currency_code'] = 'Currency must be a three-letter uppercase code.';
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }
        });
    }

    public function setBenefitsAttribute(mixed $value): void
    {
        $this->attributes['benefits'] = $this->encodeStructuredContent(
            $this->normalizeStructuredContent($value, ['description', 'icon']),
        );
    }

    public function setProcessAttribute(mixed $value): void
    {
        $items = $this->normalizeStructuredContent($value, ['description']);

        if (is_array($items)) {
            $items = array_map(
                fn (array $item, int $index): array => [
                    ...$item,
                    'step' => $index + 1,
                ],
                $items,
                array_keys($items),
            );
        }

        $this->attributes['process'] = $this->encodeStructuredContent($items);
    }

    public function setDeliverablesAttribute(mixed $value): void
    {
        $this->attributes['deliverables'] = $this->encodeStructuredContent(
            $this->normalizeStructuredContent($value, ['description']),
        );
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query
                ->where('status', self::STATUS_ACTIVE)
                ->orWhere(function (Builder $query): void {
                    $query
                        ->where('status', self::STATUS_PUBLISHED)
                        ->where(function (Builder $query): void {
                            $query
                                ->whereNull('published_at')
                                ->orWhere('published_at', '<=', now());
                        });
                });
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $this->scopePublished($query);
    }

    public function scopeAvailableForActivities(Builder $query): Builder
    {
        return $query->where('available_for_activities', true);
    }

    public function isPublished(): bool
    {
        if ($this->status === self::STATUS_ACTIVE) {
            return true;
        }

        return $this->status === self::STATUS_PUBLISHED
            && (blank($this->published_at) || $this->published_at->lte(now()));
    }

    public function resolveNavigationUrl(): ?string
    {
        return $this->isPublished()
            && filled($this->slug)
            && Route::has('services.show')
            ? route('services.show', $this->slug, absolute: false)
            : null;
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)->withTimestamps();
    }

    public function publicProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class)
            ->withTimestamps()
            ->published()
            ->orderBy('projects.sort_order')
            ->orderByDesc('projects.published_at')
            ->orderBy('projects.id');
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
        return $this->getMedia('gallery');
    }

    /**
     * @param  array<int, string>  $optionalKeys
     * @return array<int, array<string, string|null>>|null
     */
    private function normalizeStructuredContent(mixed $value, array $optionalKeys): ?array
    {
        if ($value === null) {
            return null;
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn (mixed $item): bool => is_array($item))
            ->map(function (array $item) use ($optionalKeys): array {
                $normalized = ['title' => $this->nullableString($item['title'] ?? null)];

                foreach ($optionalKeys as $key) {
                    $normalized[$key] = $this->nullableString($item[$key] ?? null);
                }

                return $normalized;
            })
            ->filter(fn (array $item): bool => filled($item['title']))
            ->values()
            ->all();
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function encodeStructuredContent(?array $value): ?string
    {
        return $value === null
            ? null
            : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
