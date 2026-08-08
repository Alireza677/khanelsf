<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    public const TYPES = [
        'site_header' => 'Site header',
        'site_footer' => 'Site footer',
        'page' => 'Page',
        'blog_index' => 'Blog index',
        'post_single' => 'Post single',
        'post_category' => 'Post category',
        'projects_index' => 'Projects index',
        'project_discovery_index' => 'گالری پروژه‌ها',
        'project_single' => 'Project single',
        'project_category' => 'Project category',
        'shop_index' => 'Shop index',
        'product_single' => 'Product single',
        'product_category' => 'Product category',
        'service_single' => 'صفحه جزئیات خدمت',
        'galleries_index' => 'Galleries index (Legacy)',
        'gallery_single' => 'Gallery single (Legacy)',
        'gallery_category' => 'Gallery category (Legacy)',
    ];

    public const LEGACY_GALLERY_TYPES = ['galleries_index', 'gallery_single', 'gallery_category'];

    public static function creatableTypes(): array
    {
        return array_diff_key(self::TYPES, array_flip(self::LEGACY_GALLERY_TYPES));
    }

    public static function editableTypeOptions(?self $template): array
    {
        if ($template && in_array($template->type, self::LEGACY_GALLERY_TYPES, true)) {
            return [
                ...self::creatableTypes(),
                $template->type => self::TYPES[$template->type],
            ];
        }

        return self::creatableTypes();
    }

    public const CONDITION_TYPES = [
        'all' => 'All items of this type',
        'specific_item' => 'Specific item',
        'category' => 'Category',
    ];

    public const ITEM_TEMPLATE_TYPES = [
        'post_single',
        'project_single',
        'product_single',
        'service_single',
        'gallery_single',
    ];

    public const CATEGORY_TEMPLATE_TYPES = [
        'post_category',
        'project_category',
        'product_category',
        'gallery_category',
    ];

    protected $fillable = [
        'title',
        'slug',
        'type',
        'status',
        'blocks',
        'priority',
        'is_default',
        'conditions',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'conditions' => 'array',
            'priority' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function hasBlocks(): bool
    {
        return collect($this->blocks)->isNotEmpty();
    }

    public function setConditionsAttribute(mixed $value): void
    {
        if (! is_array($value)) {
            $this->attributes['conditions'] = json_encode(['type' => 'all']);

            return;
        }

        $type = $value['type'] ?? 'all';

        $this->attributes['conditions'] = json_encode(match ($type) {
            'specific_item' => [
                'type' => 'specific_item',
                'item_id' => filled($value['item_id'] ?? null) ? (int) $value['item_id'] : null,
            ],
            'category' => [
                'type' => 'category',
                'category_id' => filled($value['category_id'] ?? null) ? (int) $value['category_id'] : null,
            ],
            default => ['type' => 'all'],
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function conditionType(): string
    {
        return $this->conditions['type'] ?? 'all';
    }

    public function conditionSummary(): string
    {
        $type = $this->conditionType();

        if ($type === 'specific_item') {
            return 'Specific item #'.($this->conditions['item_id'] ?? '-');
        }

        if ($type === 'category') {
            return 'Category #'.($this->conditions['category_id'] ?? '-');
        }

        return $this->is_default ? 'All / default' : 'All';
    }
}
