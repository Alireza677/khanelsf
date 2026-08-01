<?php

namespace App\Models;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\CMS\Navigation\Contracts\ResolvesNavigationUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MenuItem extends Model
{
    public const TYPE_PAGE = 'page';

    public const TYPE_CUSTOM_URL = 'custom_url';

    public const TYPE_SOURCE = 'source';

    public const TYPE_POST = 'post';

    public const TYPE_PRODUCT = 'product';

    public const TYPE_PROJECT = 'project';

    public const TYPE_SERVICE = 'service';

    public const TYPES = [
        self::TYPE_PAGE,
        self::TYPE_CUSTOM_URL,
        self::TYPE_SOURCE,
        self::TYPE_POST,
        self::TYPE_PRODUCT,
        self::TYPE_PROJECT,
        self::TYPE_SERVICE,
    ];

    protected $attributes = [
        'type' => self::TYPE_CUSTOM_URL,
    ];

    protected $fillable = [
        'menu_id',
        'parent_id',
        'type',
        'source_key',
        'reference_id',
        'reference_type',
        'title',
        'url',
        'target',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'reference_id' => 'integer',
        ];
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function resolvedUrl(): ?string
    {
        if (filled($this->source_key)) {
            return app(NavigationSourceRegistry::class)->resolve($this->source_key);
        }

        if ($this->type === self::TYPE_CUSTOM_URL) {
            return $this->url;
        }

        if (
            blank($this->reference_id)
            || blank($this->reference_type)
        ) {
            return null;
        }

        $reference = $this->relationLoaded('reference')
            ? $this->getRelation('reference')
            : rescue(fn () => $this->reference, null, report: false);

        if (! $reference instanceof ResolvesNavigationUrl) {
            return null;
        }

        return $reference->resolveNavigationUrl();
    }
}
