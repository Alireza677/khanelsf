<?php

namespace App\CMS\Blocks\SiteHeader;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\Models\Menu;
use Filament\Forms;
use Filament\Forms\Components\Component;
use InvalidArgumentException;

final class SiteHeaderBlock extends AbstractBlock implements BlockNormalizer
{
    private const ACTION_TYPES = [
        'custom_url',
        'page',
        'project',
        'product',
        'service',
        'form',
        'anchor',
        'email',
        'phone',
    ];

    public function __construct(
        private readonly SiteHeaderDataNormalizer $normalizer,
    ) {}

    public function key(): string
    {
        return 'site_header';
    }

    public function label(): string
    {
        return 'هدر صنعتی دو سطحی';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-window';
    }

    public function version(): int
    {
        return SiteHeaderDataNormalizer::SCHEMA_VERSION;
    }

    public function templates(): array
    {
        return [
            'industrial-header-v1' => new BlockTemplate(
                'industrial-header-v1',
                'هدر صنعتی دو سطحی',
                'partials.blocks.site-header-industrial',
            ),
        ];
    }

    public function defaultTemplate(): string
    {
        return 'industrial-header-v1';
    }

    public function capabilities(): array
    {
        return ['site_header_context', 'navigation', 'interactive_actions'];
    }

    public function filamentSchema(string $context): array
    {
        if ($context !== HeroBlock::CONTEXT_TEMPLATE) {
            throw new InvalidArgumentException('Site Header is only available in the Template editor.');
        }

        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default($this->version()),
            Forms\Components\Hidden::make('template')->default($this->defaultTemplate()),
            Forms\Components\Select::make('settings.menu_id')
                ->label('منوی اصلی')
                ->options(fn (): array => Menu::query()
                    ->where('status', 'active')
                    ->orderBy('title')
                    ->pluck('title', 'id')
                    ->all())
                ->searchable()
                ->preload()
                ->native(false)
                ->placeholder('استفاده از منوی اصلی تنظیمات سایت')
                ->helperText('آیتم‌ها و مقصدها همچنان توسط Menu Builder و Navigation Source Registry مدیریت می‌شوند.'),
            Forms\Components\Toggle::make('settings.search_enabled')
                ->label('نمایش جستجو')
                ->default(true),
            Forms\Components\Toggle::make('settings.sticky_enabled')
                ->label('هدر چسبان')
                ->default(true),
            Forms\Components\Toggle::make('settings.top_bar_enabled')
                ->label('نمایش نوار اقدام بالا')
                ->default(true),
            ...$this->actionFields('content.top_actions.0', 'اقدام بالایی اول', 'خدمات و پشتیبانی'),
            ...$this->actionFields('content.top_actions.1', 'اقدام بالایی دوم', 'همکاری با ما'),
            ...$this->actionFields('content.primary_action', 'اقدام اصلی', 'محاسبه هزینه ساخت'),
        ];
    }

    public function normalize(array $data): array
    {
        return $this->normalizer->normalize($data);
    }

    /** @return array<Component> */
    private function actionFields(string $path, string $title, string $defaultLabel): array
    {
        return [
            Forms\Components\TextInput::make("{$path}.label")
                ->label("متن {$title}")
                ->default($defaultLabel)
                ->maxLength(255),
            ActionPicker::make("{$path}.action")
                ->label("مقصد {$title}")
                ->allowedTypes(self::ACTION_TYPES)
                ->columnSpanFull(),
        ];
    }
}
