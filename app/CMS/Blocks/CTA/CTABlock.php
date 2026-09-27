<?php

namespace App\CMS\Blocks\CTA;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\CMS\Blocks\Support\HeadingLevel;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use Filament\Forms;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;

final class CTABlock extends AbstractBlock
{
    use UsesMediaLibraryImages;

    public function key(): string
    {
        return 'cta';
    }

    public function label(): string
    {
        return 'دعوت به اقدام';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-megaphone';
    }

    public function version(): int
    {
        return CTADataNormalizer::SCHEMA_VERSION;
    }

    public function templates(): array
    {
        return [
            'classic' => new BlockTemplate('classic', 'قالب ساده', 'partials.blocks.cta'),
            'image' => new BlockTemplate('image', 'قالب تصویری', 'partials.blocks.cta'),
        ];
    }

    public function defaultTemplate(): string
    {
        return 'classic';
    }

    public function capabilities(): array
    {
        return ['media', 'primary_cta', 'secondary_cta', 'form_actions'];
    }

    public function filamentBlock(string $context): Block
    {
        $block = parent::filamentBlock($context)->columns(2);

        return $context === HeroBlock::CONTEXT_TEMPLATE ? $block->label(__('Static: CTA')) : $block;
    }

    public function filamentSchema(string $context): array
    {
        $this->guardContext($context);
        $page = $context === HeroBlock::CONTEXT_PAGE;

        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default(CTADataNormalizer::SCHEMA_VERSION),
            Forms\Components\Select::make('template')
                ->label($page ? 'قالب دعوت به اقدام' : __('CTA template'))
                ->options($page ? ['classic' => 'قالب ساده', 'image' => 'قالب تصویری'] : ['classic' => 'قالب فعلی', 'image' => 'قالب تصویری'])
                ->default('classic')->required()->live(),
            Forms\Components\TextInput::make('settings.content_width')
                ->label($page ? 'عرض بخش متن' : __('Content width'))->numeric()->minValue(240)->maxValue(1400)->default(580)->suffix('px')
                ->visible(fn (Get $get): bool => $get('template') === 'image'),
            Forms\Components\Select::make('settings.background')
                ->label($page ? 'پس‌زمینه بخش' : __('Section background'))
                ->options($page ? ['default' => 'پیش‌فرض', 'muted' => 'ملایم', 'dark' => 'تیره'] : ['default' => __('Default'), 'muted' => __('Muted'), 'dark' => __('Dark')])
                ->default('default')->visible(fn (Get $get): bool => $get('template') === 'classic'),
            Forms\Components\Select::make('settings.alignment')
                ->label($page ? 'چیدمان' : __('Alignment'))->options($page ? ['left' => 'چپ', 'center' => 'وسط'] : ['left' => __('Left'), 'center' => __('Center')])
                ->default('center')->visible(fn (Get $get): bool => $get('template') === 'classic'),
            Forms\Components\TextInput::make('content.eyebrow')->label($page ? 'برچسب بالای عنوان' : __('Eyebrow'))->maxLength(255)
                ->visible(fn (Get $get): bool => $get('template') === 'classic'),
            Forms\Components\ViewField::make('content.media.url')->label($page ? 'تصویر پس‌زمینه' : __('Background image'))
                ->view('filament.forms.components.media-library-url-picker')
                ->viewData(fn (): array => ['images' => self::mediaLibraryImageItems()])
                ->visible(fn (Get $get): bool => $get('template') === 'image')->columnSpanFull(),
            $this->mediaSettings($page),
            Forms\Components\TextInput::make('content.title')
                ->label($page ? 'عنوان' : __('Title'))
                ->required($page)
                ->helperText($page ? null : __('Optional in templates; an empty CTA block does not render.'))
                ->maxLength(255),
            HeadingLevel::field('settings.heading_tag', $page ? 'تگ عنوان' : __('Heading tag')),
            Forms\Components\RichEditor::make('content.description')->label($page ? 'توضیحات' : __('Description'))->columnSpanFull(),
            ...$this->actionFields('primary_cta', $page, false),
            ...$this->actionFields('secondary_cta', $page, true),
        ];
    }

    private function actionFields(string $name, bool $page, bool $secondary): array
    {
        $prefix = "content.{$name}";
        $visible = fn (Get $get): bool => ! $secondary || $get('template') === 'image';

        return [
            Forms\Components\TextInput::make("{$prefix}.label")
                ->label($secondary ? __('Secondary button label') : __('Primary button label'))
                ->maxLength(255)
                ->visible($visible),
            ActionPicker::make("{$prefix}.action")
                ->label($secondary ? __('Secondary button destination') : __('Primary button destination'))
                ->allowedTypes([
                    'custom_url',
                    'page',
                    'project',
                    'product',
                    'service',
                    'form',
                    'anchor',
                    'email',
                    'phone',
                ])
                ->visible($visible)
                ->columnSpanFull(),
        ];
    }

    private function guardContext(string $context): void
    {
        if (! in_array($context, [HeroBlock::CONTEXT_PAGE, HeroBlock::CONTEXT_TEMPLATE], true)) {
            throw new \InvalidArgumentException("Unsupported CTA editor context [{$context}].");
        }
    }

    private function mediaSettings(bool $page): Component
    {
        return Forms\Components\Section::make($page ? 'تنظیمات تصویر پس‌زمینه' : __('Background image settings'))
            ->schema([
                Forms\Components\Grid::make(['default' => 1, 'xl' => 2])->schema([
                    $this->deviceSettings('settings.media.desktop', $page ? 'دسکتاپ' : __('Desktop'), $page),
                    $this->deviceSettings('settings.media.mobile', $page ? 'موبایل' : __('Mobile'), $page),
                ]),
            ])->collapsible()->collapsed()->columnSpanFull()
            ->visible(fn (Get $get): bool => $get('template') === 'image');
    }

    private function deviceSettings(string $path, string $label, bool $page): Component
    {
        return Forms\Components\Section::make($label)->schema([
            Forms\Components\TextInput::make("{$path}.width.value")->label($page ? 'عرض' : __('Width'))->numeric()->minValue(0)->columnSpan(2),
            Forms\Components\Select::make("{$path}.width.unit")->label($page ? 'واحد عرض' : __('Width unit'))->options(['%' => '%', 'px' => 'px'])->default('%'),
            Forms\Components\TextInput::make("{$path}.height.value")->label($page ? 'ارتفاع' : __('Height'))->numeric()->minValue(0)->columnSpan(2),
            Forms\Components\Select::make("{$path}.height.unit")->label($page ? 'واحد ارتفاع' : __('Height unit'))->options(['%' => '%', 'px' => 'px'])->default('px'),
            Forms\Components\Select::make("{$path}.fit")->label($page ? 'واکنش تصویر' : __('Image fit'))
                ->options($page ? ['normal' => 'عادی', 'cover' => 'پوشش', 'contain' => 'کامل دیده شود'] : ['normal' => __('Normal'), 'cover' => __('Cover'), 'contain' => __('Contain')])
                ->default('normal')->columnSpanFull(),
        ])->columns(6);
    }
}
