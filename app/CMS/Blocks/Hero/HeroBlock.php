<?php

namespace App\CMS\Blocks\Hero;

use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\CMS\Blocks\Support\HeadingLevel;
use App\Filament\Resources\Concerns\UsesIconsaxIconPicker;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;

final class HeroBlock extends AbstractBlock
{
    use UsesIconsaxIconPicker;
    use UsesMediaLibraryImages;

    public const CONTEXT_PAGE = 'page';

    public const CONTEXT_TEMPLATE = 'template';

    public function key(): string
    {
        return 'hero';
    }

    public function label(): string
    {
        return 'هیرو';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public function version(): int
    {
        return HeroDataNormalizer::SCHEMA_VERSION;
    }

    public function templates(): array
    {
        return [
            'default' => new BlockTemplate('default', 'پیش‌فرض', 'partials.blocks.hero.default'),
            'hero_1' => new BlockTemplate('hero_1', 'هیرو ۱ - تصویر پس‌زمینه کامل', 'partials.blocks.hero.hero_1'),
            'hero_2' => new BlockTemplate('hero_2', 'هیرو ۲ - دعوت به اقدام انتخابی', 'partials.blocks.hero.hero_2'),
            'hero_3' => new BlockTemplate('hero_3', 'هیرو ۳ - تصویر و آمار دو ستونه', 'partials.blocks.hero.hero_3'),
        ];
    }

    public function defaultTemplate(): string
    {
        return 'default';
    }

    public function renderView(array $data): string
    {
        // The legacy-compatible root view normalizes the payload before it
        // delegates to the selected Hero BlockTemplate view.
        return 'partials.blocks.hero';
    }

    public function capabilities(): array
    {
        return ['media', 'primary_cta', 'secondary_cta', 'stats'];
    }

    public function filamentSchema(string $context): array
    {
        $this->guardContext($context);

        if (config('cms.hero_v2_editor_runtime') ?? config('cms.hero_v2_editor', false)) {
            return app(HeroV2EditorSchema::class)->schema($context, $this->templates());
        }

        return [
            $this->templateSelector($context),
            ...$this->defaultTemplateFields($context),
            ...$this->heroOneFields($context),
            ...$this->heroTwoFields($context),
            ...$this->heroThreeFields($context),
            ...$this->commonContentFields($context),
            ...$this->selectorFields($context),
            ...$this->statsFields($context),
            ...$this->heroOneFooterFields($context),
            ...$this->mediaFields($context),
        ];
    }

    private function templateSelector(string $context): Forms\Components\Select
    {
        return Forms\Components\Select::make('template')
            ->label($this->text($context, 'قالب', 'Template'))
            ->options(collect($this->templates())->mapWithKeys(fn (BlockTemplate $template): array => [
                $template->key => $context === self::CONTEXT_PAGE
                    ? $template->label
                    : match ($template->key) {
                        'default' => 'Default',
                        'hero_1' => 'Hero 1 - full background image',
                        'hero_2' => 'Hero 2 - selector CTA',
                        'hero_3' => 'Hero 3 - split image and stats',
                    },
            ])->all())
            ->default($this->defaultTemplate())
            ->live()
            ->helperText($context === self::CONTEXT_PAGE ? 'بلوک‌های هیروی قدیمی که قالب ندارند از حالت پیش‌فرض استفاده می‌کنند.' : null);
    }

    /** @return array<Component> */
    private function defaultTemplateFields(string $context): array
    {
        if ($context === self::CONTEXT_TEMPLATE) {
            return [];
        }

        return [
            Forms\Components\Select::make('section_background')
                ->label('پس‌زمینه بخش')
                ->options(['default' => 'پیش‌فرض', 'muted' => 'ملایم', 'dark' => 'تیره'])
                ->default('default')
                ->visible(fn (Get $get): bool => blank($get('template')) || $get('template') === 'default'),
            Forms\Components\Select::make('alignment')
                ->label('چیدمان')
                ->options(['left' => 'چپ', 'center' => 'وسط'])
                ->default('center')
                ->visible(fn (Get $get): bool => blank($get('template')) || $get('template') === 'default'),
        ];
    }

    /** @return array<Component> */
    private function heroOneFields(string $context): array
    {
        return [
            Forms\Components\TextInput::make('eyebrow')
                ->label($this->text($context, 'برچسب بالای عنوان', 'Eyebrow'))
                ->maxLength(255)
                ->helperText($context === self::CONTEXT_PAGE ? 'یک برچسب کوتاه اختیاری بالای عنوان.' : null)
                ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
            self::iconsaxIconPicker('hero_1_eyebrow_icon', $this->text($context, 'آیکن برچسب', 'Eyebrow icon'), $context === self::CONTEXT_PAGE ? 'یک آیکن Iconsax اختیاری کنار برچسب بالای عنوان.' : null)
                ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
            self::iconsaxIconSizeInput('hero_1_eyebrow_icon_size', $context === self::CONTEXT_TEMPLATE ? 'Size' : null)
                ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
            $this->heroThemeSelector($context),
            Forms\Components\ViewField::make('hero_1_theme_loading')
                ->view('filament.forms.components.hero-view-loading')
                ->dehydrated(false)->hiddenLabel()->columnSpanFull()
                ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
            $this->dottedBackgroundSection($context),
            $this->animatedPathsSection($context),
            Forms\Components\TextInput::make('hero_1_title_second_line')
                ->label($this->text($context, 'خط دوم عنوان', 'Hero 1 title second line'))
                ->maxLength(255)
                ->helperText($context === self::CONTEXT_PAGE ? 'برای نمایش عنوان در دو خط، مثل نام شخص یا عبارت تاکیدشده.' : null)
                ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
            Forms\Components\Toggle::make('hero_1_show_underline')
                ->label($this->text($context, 'نمایش خط تاکید زیر عنوان', 'Show Hero 1 underline'))
                ->default(false)
                ->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
            $this->heroOneSizeFieldset($context),
        ];
    }

    private function heroThemeSelector(string $context): Forms\Components\Select
    {
        return Forms\Components\Select::make('hero_1_theme')
            ->label($this->text($context, 'نمای هیرو ۱', 'Hero 1 appearance'))
            ->options($context === self::CONTEXT_PAGE
                ? ['image' => 'تصویر تیره', 'light_grid' => 'روشن شبکه‌ای', 'animated_dotted_surface' => 'پس‌زمینه نقطه‌ای متحرک', 'animated_paths' => 'مسیرهای متحرک']
                : ['image' => 'Dark image', 'light_grid' => 'Light grid', 'animated_dotted_surface' => 'Animated dotted surface', 'animated_paths' => 'Animated paths'])
            ->default('image')->live()
            ->afterStateUpdated(fn ($livewire) => $livewire->skipRender())
            ->extraInputAttributes(fn (Forms\Components\Select $component): array => [
                'wire:loading.attr' => 'disabled',
                'wire:target' => $component->getStatePath(),
            ])
            ->helperText($context === self::CONTEXT_PAGE ? 'حالت روشن شبکه‌ای برای هیروهایی شبیه نمونه ارسالی است. حالت تصویر تیره با نسخه قبلی سازگار می‌ماند.' : null)
            ->visible(fn (Get $get): bool => $get('template') === 'hero_1');
    }

    private function dottedBackgroundSection(string $context): Forms\Components\Section
    {
        return Forms\Components\Section::make($this->text($context, 'تنظیمات پس‌زمینه متحرک', 'Animated background settings'))
            ->schema([
                Forms\Components\Toggle::make('animated_background_enabled')->label($this->text($context, 'فعال‌سازی پس‌زمینه متحرک', 'Enable animated background'))->default(true),
                Forms\Components\Toggle::make('animated_background_interactive')->label($this->text($context, 'واکنش به حرکت موس', 'React to pointer movement'))->default(true),
                Forms\Components\Select::make('animated_background_density')->label($this->text($context, 'تراکم نقاط', 'Dot density'))->options($this->speedOptions($context, density: true))->default('medium'),
                Forms\Components\Select::make('animated_background_speed')->label($this->text($context, 'سرعت حرکت', 'Animation speed'))->options($this->speedOptions($context))->default('slow'),
                Forms\Components\TextInput::make('animated_background_opacity')->label($this->text($context, 'شفافیت نقاط', 'Dot opacity'))->numeric()->minValue(0.1)->maxValue(1)->step(0.05)->default(0.45),
                Forms\Components\ColorPicker::make('animated_background_color')->label($this->text($context, 'رنگ پس‌زمینه انیمیشن', 'Animation background color'))->default('#08132a'),
                Forms\Components\ColorPicker::make('animated_dots_color')->label($this->text($context, 'رنگ نقطه‌ها', 'Dot color'))->default('#dbe7ff'),
            ])->columns(2)->columnSpanFull()
            ->visible(fn (Get $get): bool => $get('template') === 'hero_1')
            ->extraAttributes(fn (Forms\Components\Section $component): array => $this->heroThemeSectionAttributes($component, 'animated_dotted_surface'));
    }

    private function animatedPathsSection(string $context): Forms\Components\Section
    {
        return Forms\Components\Section::make($this->text($context, 'تنظیمات مسیرهای متحرک', 'Animated paths settings'))
            ->schema([
                Forms\Components\ColorPicker::make('paths_background_color')->label($this->text($context, 'رنگ پس‌زمینه', 'Background color'))->default('#0b1220'),
                Forms\Components\ColorPicker::make('paths_color')->label($this->text($context, 'رنگ خطوط', 'Line color'))->default('#ffffff'),
                Forms\Components\TextInput::make('paths_opacity')->label($this->text($context, 'شفافیت خطوط', 'Line opacity'))->numeric()->minValue(0.05)->maxValue(1)->step(0.05)->default(0.35),
                Forms\Components\Select::make('paths_speed')->label($this->text($context, 'سرعت حرکت خطوط', 'Animation speed'))->options($this->speedOptions($context))->default('normal'),
                Forms\Components\Select::make('paths_density')->label($this->text($context, 'تراکم خطوط', 'Line density'))->options($this->speedOptions($context, density: true))->default('medium'),
                Forms\Components\TextInput::make('paths_line_width')->label($this->text($context, 'ضخامت خطوط', 'Line width'))->numeric()->minValue(0.2)->maxValue(3)->step(0.1)->default(1),
                Forms\Components\Toggle::make('paths_animation_enabled')->label($this->text($context, 'فعال‌سازی حرکت خطوط', 'Enable line animation'))->default(true),
            ])->columns(2)->columnSpanFull()
            ->visible(fn (Get $get): bool => $get('template') === 'hero_1')
            ->extraAttributes(fn (Forms\Components\Section $component): array => $this->heroThemeSectionAttributes($component, 'animated_paths'));
    }

    private function heroOneSizeFieldset(string $context): Forms\Components\Fieldset
    {
        return Forms\Components\Fieldset::make($this->text($context, 'سایز', 'Hero 1 size'))
            ->schema([
                Forms\Components\TextInput::make('hero_1_desktop_height')->label($this->text($context, 'ارتفاع دسکتاپ', 'Desktop height'))->numeric()->minValue(0)->suffix('px')->prefixIcon('heroicon-o-computer-desktop')->placeholder($this->text($context, 'مثلا 560', 'Example: 560')),
                Forms\Components\TextInput::make('hero_1_mobile_height')->label($this->text($context, 'ارتفاع موبایل', 'Mobile height'))->numeric()->minValue(0)->suffix('px')->prefixIcon('heroicon-o-device-phone-mobile')->placeholder($this->text($context, 'مثلا 460', 'Example: 460')),
            ])->columns(2)->visible(fn (Get $get): bool => $get('template') === 'hero_1')->columnSpanFull();
    }

    /** @return array<Component> */
    private function heroTwoFields(string $context): array
    {
        $imageVisible = fn (Get $get): bool => ($get('hero_2_background_type') ?: (filled($get('hero_2_video_url')) ? 'video' : 'image')) === 'image';
        $videoVisible = fn (Get $get): bool => ! $imageVisible($get);

        return [
            Forms\Components\Select::make('hero_2_alignment')->label($this->text($context, 'چیدمان محتوا', 'Alignment'))->options($context === self::CONTEXT_PAGE ? ['left' => 'چپ', 'right' => 'راست'] : ['left' => 'Left', 'right' => 'Right'])->default('left')->helperText($context === self::CONTEXT_PAGE ? 'فقط برای هیرو ۲. مشخص می‌کند محتوا در کدام سمت قرار بگیرد.' : null)->visible(fn (Get $get): bool => $get('template') === 'hero_2'),
            Forms\Components\TextInput::make('hero_2_height')->label($this->text($context, 'ارتفاع بلوک', 'Block height'))->numeric()->minValue(0)->suffix('px')->placeholder($this->text($context, 'مثلا 560', 'Example: 560'))->helperText($context === self::CONTEXT_PAGE ? 'فقط برای هیرو ۲. اگر خالی باشد ارتفاع پیش‌فرض استفاده می‌شود.' : null)->visible(fn (Get $get): bool => $get('template') === 'hero_2')->columnSpan(1),
            Forms\Components\Section::make($this->text($context, 'پس‌زمینه هیرو ۲', 'Hero 2 background'))
                ->schema([
                    Forms\Components\Select::make('hero_2_background_type')->label($this->text($context, 'نوع پس‌زمینه', 'Background type'))->options($context === self::CONTEXT_PAGE ? ['image' => 'تصویر', 'video' => 'ویدیو'] : ['image' => 'Image', 'video' => 'Video'])->default('image')->afterStateHydrated(function (Forms\Components\Select $component, ?string $state, Get $get): void {
                        if (blank($state) && filled($get('hero_2_video_url'))) {
                            $component->state('video');
                        }
                    })->live(),
                    Forms\Components\ViewField::make('image')->label($this->text($context, 'تصویر پس‌زمینه', 'Background image'))->view('filament.forms.components.media-library-url-picker')->viewData(fn (): array => ['images' => self::mediaLibraryImageItems()])->visible($imageVisible)->columnSpanFull(),
                    ...($context === self::CONTEXT_PAGE ? $this->imageSettingsFields('image', 'تنظیمات تصویر پس‌زمینه', $imageVisible) : []),
                    Forms\Components\ViewField::make('hero_2_video_url')->label($this->text($context, 'ویدیوی پس‌زمینه', 'Background video'))->view('filament.forms.components.media-library-video-url-picker')->viewData(fn (): array => ['videos' => self::mediaLibraryVideoItems()])->visible($videoVisible)->columnSpanFull(),
                    Forms\Components\ViewField::make('hero_2_video_poster')->label($this->text($context, 'تامبنیل ویدیو', 'Video thumbnail'))->view('filament.forms.components.media-library-url-picker')->viewData(fn (): array => ['images' => self::mediaLibraryImageItems()])->visible($videoVisible)->columnSpanFull(),
                ])->visible(fn (Get $get): bool => $get('template') === 'hero_2')->columns(2)->columnSpanFull(),
        ];
    }

    /** @return array<Component> */
    private function heroThreeFields(string $context): array
    {
        return [Forms\Components\Select::make('hero_3_alignment')->label($this->text($context, 'چیدمان محتوا', 'Alignment'))->options($context === self::CONTEXT_PAGE ? ['left' => 'چپ', 'right' => 'راست'] : ['left' => 'Left', 'right' => 'Right'])->default('right')->visible(fn (Get $get): bool => $get('template') === 'hero_3')];
    }

    /** @return array<Component> */
    private function commonContentFields(string $context): array
    {
        return [
            Forms\Components\TextInput::make('title')->label($this->text($context, 'عنوان', 'Title'))->required()->maxLength(255),
            HeadingLevel::field('heading_tag', $this->text($context, 'تگ عنوان', 'Heading tag')),
            Forms\Components\TextInput::make('subtitle')->label($this->text($context, 'زیرعنوان', 'Subtitle'))->maxLength(255),
            Forms\Components\Textarea::make('description')->label($this->text($context, 'توضیحات', 'Description'))->rows($context === self::CONTEXT_PAGE ? 4 : 3)->columnSpanFull(),
            Forms\Components\TextInput::make('primary_button_label')->label($this->text($context, 'متن دکمه اصلی', 'Primary button label'))->maxLength(255),
            Forms\Components\TextInput::make('primary_button_url')->label($this->text($context, 'لینک دکمه اصلی', 'Primary button URL'))->maxLength(255),
            Forms\Components\TextInput::make('secondary_button_label')->label($this->text($context, 'متن دکمه دوم', 'Secondary button label'))->maxLength(255),
            Forms\Components\TextInput::make('secondary_button_url')->label($this->text($context, 'لینک دکمه دوم', 'Secondary button URL'))->maxLength(255),
        ];
    }

    /** @return array<Component> */
    private function selectorFields(string $context): array
    {
        return [
            ...($context === self::CONTEXT_PAGE ? [Forms\Components\TextInput::make('selector_placeholder')->label('متن پیش‌فرض انتخابگر')->default('دنبال چه چیزی هستید؟')->maxLength(255)->visible(fn (Get $get): bool => $get('template') === 'hero_2')] : []),
            Forms\Components\Repeater::make('selector_items')->label($this->text($context, 'گزینه‌های انتخابگر', 'Selector items'))->cloneable()->schema([
                Forms\Components\TextInput::make('label')->label($this->text($context, 'عنوان گزینه', 'Label'))->required()->maxLength(255),
                Forms\Components\TextInput::make('url')->label($this->text($context, 'لینک', 'URL'))->required()->maxLength(255),
            ])->defaultItems(0)->visible(fn (Get $get): bool => $get('template') === 'hero_2')->columnSpanFull()->columns(2),
        ];
    }

    /** @return array<Component> */
    private function statsFields(string $context): array
    {
        return [Forms\Components\Repeater::make('stats')->label($this->text($context, 'آمار', 'Stats'))->cloneable()->schema([
            Forms\Components\TextInput::make('value')->label($this->text($context, 'مقدار', 'Value'))->required()->maxLength(80),
            Forms\Components\TextInput::make('label')->label($this->text($context, 'عنوان', 'Label'))->required()->maxLength(120),
            Forms\Components\TextInput::make('description')->label($this->text($context, 'توضیحات', 'Description'))->maxLength(160),
            self::iconsaxIconPicker('icon', $this->text($context, 'آیکن', 'Icon')),
            self::iconsaxIconSizeInput(label: $context === self::CONTEXT_TEMPLATE ? 'Size' : null),
        ])->defaultItems(0)->visible(fn (Get $get): bool => $get('template') === 'hero_3')->columnSpanFull()->columns(5)];
    }

    /** @return array<Component> */
    private function heroOneFooterFields(string $context): array
    {
        return [
            Forms\Components\Repeater::make('hero_1_social_links')->label($this->text($context, 'لینک‌های پایین هیرو', 'Hero 1 bottom links'))->cloneable()->schema([
                Forms\Components\TextInput::make('label')->label($this->text($context, 'عنوان', 'Label'))->required()->maxLength(120),
                Forms\Components\TextInput::make('url')->label($this->text($context, 'لینک', 'URL'))->required()->maxLength(255),
                self::iconsaxIconPicker('icon', $this->text($context, 'آیکن', 'Icon')),
                self::iconsaxIconSizeInput(label: $context === self::CONTEXT_TEMPLATE ? 'Size' : null),
            ])->defaultItems(0)->visible(fn (Get $get): bool => $get('template') === 'hero_1')->columnSpanFull()->columns(4),
            Forms\Components\TextInput::make('hero_1_scroll_label')->label($this->text($context, 'متن اسکرول', 'Hero 1 scroll label'))->maxLength(120)->visible(fn (Get $get): bool => $get('template') === 'hero_1'),
        ];
    }

    /** @return array<Component> */
    private function mediaFields(string $context): array
    {
        return [
            Forms\Components\ViewField::make('image')->label($this->text($context, 'تصویر', 'Image'))->view('filament.forms.components.media-library-url-picker')->viewData(fn (): array => ['images' => self::mediaLibraryImageItems()])->visible(fn (Get $get): bool => $get('template') !== 'hero_2')->columnSpanFull(),
            ...($context === self::CONTEXT_PAGE ? $this->imageSettingsFields('image', 'تنظیمات تصویر', fn (Get $get): bool => $get('template') !== 'hero_2') : []),
            ...($context === self::CONTEXT_PAGE ? [Forms\Components\TextInput::make('overlay_opacity')->label('شفافیت پوشش')->numeric()->minValue(0)->maxValue(90)->default(45)->suffix('%')->visible(fn (Get $get): bool => $get('template') === 'hero_1')] : []),
        ];
    }

    /** @return array<Component> */
    private function imageSettingsFields(string $prefix, string $label, \Closure $visible): array
    {
        return [Forms\Components\Section::make($label)->schema([
            Forms\Components\Grid::make(['default' => 1, 'xl' => 2])->schema([
                Forms\Components\Section::make('دسکتاپ')->schema($this->imageDeviceSettingsFields($prefix))->columns(6),
                Forms\Components\Section::make('موبایل')->schema($this->imageDeviceSettingsFields($prefix, 'mobile'))->columns(6),
            ]),
        ])->collapsible()->collapsed()->columnSpanFull()->visible($visible)];
    }

    /** @return array<Component> */
    private function imageDeviceSettingsFields(string $prefix, ?string $device = null): array
    {
        $key = $device === 'mobile' ? "{$prefix}_mobile" : $prefix;

        return [
            Forms\Components\TextInput::make("{$key}_width_value")->label('عرض')->numeric()->minValue(0)->placeholder('مثلا 100')->columnSpan(2),
            Forms\Components\Select::make("{$key}_width_unit")->label('واحد عرض')->options(['%' => 'درصد', 'px' => 'پیکسل'])->default('%')->columnSpan(1),
            Forms\Components\TextInput::make("{$key}_height_value")->label('ارتفاع')->numeric()->minValue(0)->placeholder('مثلا 240')->columnSpan(2),
            Forms\Components\Select::make("{$key}_height_unit")->label('واحد ارتفاع')->options(['%' => 'درصد', 'px' => 'پیکسل'])->default('px')->columnSpan(1),
            Forms\Components\Select::make("{$key}_fit")->label('واکنش تصویر')->options(['normal' => 'عادی', 'cover' => 'پوشش', 'contain' => 'کامل دیده شود'])->default('normal')->columnSpanFull(),
        ];
    }

    private function heroThemeSectionAttributes(Forms\Components\Section $component, string $theme): array
    {
        $statePath = $component->getStatePath().'.hero_1_theme';

        return ['x-data' => "{ heroTheme: \$wire.entangle('{$statePath}') }", 'x-show' => "heroTheme === '{$theme}'", 'x-cloak' => true];
    }

    private function text(string $context, string $page, string $template): string
    {
        return $context === self::CONTEXT_PAGE ? $page : $template;
    }

    private function speedOptions(string $context, bool $density = false): array
    {
        if ($context === self::CONTEXT_TEMPLATE) {
            return ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'];
        }

        return $density ? ['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'] : ['slow' => 'آرام', 'normal' => 'معمولی', 'fast' => 'سریع'];
    }

    private function guardContext(string $context): void
    {
        if (! in_array($context, [self::CONTEXT_PAGE, self::CONTEXT_TEMPLATE], true)) {
            throw new \InvalidArgumentException("Unsupported Hero schema context [{$context}].");
        }
    }
}
