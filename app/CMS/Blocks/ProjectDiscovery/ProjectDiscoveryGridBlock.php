<?php

namespace App\CMS\Blocks\ProjectDiscovery;

use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use Filament\Forms;
use InvalidArgumentException;

final class ProjectDiscoveryGridBlock extends AbstractBlock implements BlockNormalizer
{
    public const SCHEMA_VERSION = 1;

    public function key(): string { return 'project_discovery_grid'; }

    public function label(): string { return 'گالری پروژه‌ها'; }

    public function icon(): ?string { return 'heroicon-o-squares-2x2'; }

    public function version(): int { return self::SCHEMA_VERSION; }

    public function templates(): array
    {
        return ['default' => new BlockTemplate('default', 'پیش‌فرض', 'partials.blocks.project_discovery_grid')];
    }

    public function defaultTemplate(): string { return 'default'; }

    public function capabilities(): array { return ['project_discovery_context', 'dynamic_data']; }

    public function filamentSchema(string $context): array
    {
        if ($context !== HeroBlock::CONTEXT_TEMPLATE) {
            throw new InvalidArgumentException('Project Discovery Grid is only available in the template editor.');
        }

        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default(self::SCHEMA_VERSION),
            Forms\Components\Hidden::make('template')->default('default'),
            Forms\Components\Toggle::make('settings.show_filters')->label('نمایش فیلترها')->default(true),
            Forms\Components\Select::make('settings.columns')->label('تعداد ستون‌ها')->options([2 => '۲', 3 => '۳', 4 => '۴'])->default(3)->required(),
            Forms\Components\Select::make('settings.image_ratio')->label('نسبت تصویر')->options(['landscape' => 'افقی', 'square' => 'مربع', 'portrait' => 'عمودی'])->default('landscape')->required(),
            Forms\Components\Toggle::make('settings.show_category')->label('نمایش دسته پروژه')->default(true),
            Forms\Components\Toggle::make('settings.show_discovery_terms')->label('نمایش برچسب‌های کشف')->default(true),
        ];
    }

    public function normalize(array $data): array
    {
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];

        return [
            'block_id' => is_string($data['block_id'] ?? null) ? $data['block_id'] : null,
            'schema_version' => self::SCHEMA_VERSION,
            'template' => 'default',
            'settings' => [
                'show_filters' => is_bool($settings['show_filters'] ?? null) ? $settings['show_filters'] : true,
                'columns' => in_array((int) ($settings['columns'] ?? 3), [2, 3, 4], true) ? (int) $settings['columns'] : 3,
                'image_ratio' => in_array($settings['image_ratio'] ?? 'landscape', ['landscape', 'square', 'portrait'], true) ? $settings['image_ratio'] : 'landscape',
                'show_category' => is_bool($settings['show_category'] ?? null) ? $settings['show_category'] : true,
                'show_discovery_terms' => is_bool($settings['show_discovery_terms'] ?? null) ? $settings['show_discovery_terms'] : true,
            ],
        ];
    }
}
