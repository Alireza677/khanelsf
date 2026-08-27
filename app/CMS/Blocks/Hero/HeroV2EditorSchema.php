<?php

namespace App\CMS\Blocks\Hero;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\Support\BlockTemplate;
use App\CMS\Blocks\Support\HeadingLevel;
use App\Filament\Resources\Concerns\UsesIconsaxIconPicker;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;
use Filament\Forms\Set;

final class HeroV2EditorSchema
{
    use UsesIconsaxIconPicker;
    use UsesMediaLibraryImages;

    private const MODE_IMAGE = 'image';

    private const MODE_LIGHT_GRID = 'light_grid';

    private const MODE_DOTS = 'animated_dotted_surface';

    private const MODE_PATHS = 'animated_paths';

    private const ACTION_TYPES = ['custom_url', 'page', 'project', 'product', 'service', 'form', 'anchor', 'email', 'phone'];

    /** @param array<string, BlockTemplate> $templates
     * @return array<Component>
     */
    public function schema(string $context, array $templates): array
    {
        $page = $context === HeroBlock::CONTEXT_PAGE;
        $templateIs = fn (Get $get, string $template): bool => ($get('template') ?: 'default') === $template;

        $schema = [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default(HeroDataNormalizer::SCHEMA_VERSION),
            Forms\Components\Select::make('template')
                ->label($page ? 'قالب' : 'Template')
                ->options(function (Get $get) use ($templates, $page): array {
                    $options = collect($templates)->mapWithKeys(fn (BlockTemplate $template): array => [
                        $template->key => $page ? $template->label : $template->key,
                    ])->all();
                    $current = $get('template');

                    if (is_string($current) && $current !== '' && ! array_key_exists($current, $options)) {
                        $options[$current] = ($page ? 'قالب ناشناخته: ' : 'Unknown template: ').$current;
                    }

                    return $options;
                })
                ->default('default')->live(),

            Forms\Components\Toggle::make('content.eyebrow.enabled')->label('نمایش برچسب بالای عنوان')->default(false)->live()->visible(fn (Get $get): bool => $page && $templateIs($get, 'hero_1')),
            Forms\Components\Grid::make(['default' => 1, 'xl' => 2])->schema([
                Forms\Components\TextInput::make('content.eyebrow.text')->label($page ? 'برچسب بالای عنوان' : 'Eyebrow')->maxLength(255),
                self::iconsaxIconPicker('content.eyebrow.icon', $page ? 'آیکن برچسب' : 'Eyebrow icon'),
            ])->visible(fn (Get $get): bool => in_array($get('template'), ['hero_1', 'hero_3'], true) && (! $page || ! $templateIs($get, 'hero_1') || (bool) $get('content.eyebrow.enabled')))->columnSpanFull(),
            self::iconsaxIconSizeInput('settings.eyebrow_icon_size', $page ? null : 'Size')->visible(fn (Get $get): bool => in_array($get('template'), ['hero_1', 'hero_3'], true) && (! $page || ! $templateIs($get, 'hero_1') || (bool) $get('content.eyebrow.enabled'))),
            Forms\Components\TextInput::make('content.title')->label($page ? 'عنوان' : 'Title')->required()->maxLength(255),
            Forms\Components\Toggle::make('content.title_secondary_enabled')->label('نمایش خط دوم عنوان')->default(false)->live()->visible(fn (Get $get): bool => $page && $templateIs($get, 'hero_1')),
            Forms\Components\Grid::make(['default' => 1, 'xl' => 2])->schema([
                Forms\Components\TextInput::make('content.title_secondary')->label($page ? 'خط دوم عنوان' : 'Title second line')->maxLength(255),
                Forms\Components\Toggle::make('content.title_secondary_underline')->label($page ? 'نمایش خط تأکید زیر عنوان' : 'Underline second title')->default(false),
            ])->visible(fn (Get $get): bool => $templateIs($get, 'hero_1') && (! $page || (bool) $get('content.title_secondary_enabled')))->columnSpanFull(),
            Forms\Components\TextInput::make('content.lead')->label($page ? 'زیرعنوان' : 'Lead')->maxLength(255),
            Forms\Components\RichEditor::make('content.description')->label($page ? 'توضیحات' : 'Description')->columnSpanFull(),
            HeadingLevel::field('settings.heading_tag', $page ? 'تگ عنوان' : 'Heading tag'),
            Forms\Components\Select::make('settings.alignment')->label($page ? 'چیدمان' : 'Alignment')->options(['left' => $page ? 'چپ' : 'Left', 'right' => $page ? 'راست' : 'Right', 'center' => $page ? 'وسط' : 'Center', 'start' => 'Start', 'end' => 'End'])->default('left')->visible(fn (Get $get): bool => ! $templateIs($get, 'hero_2')),
            Forms\Components\Select::make('settings.color_mode')->label($page ? 'حالت رنگ' : 'Color mode')->options(['default' => $page ? 'پیش‌فرض' : 'Default', 'muted' => $page ? 'ملایم' : 'Muted', 'dark' => $page ? 'تیره' : 'Dark'])->default('default'),

            ...$this->ctaFields($page, $templateIs),

            $this->themeSelector($page, $templateIs),
            Forms\Components\Hidden::make('settings.background_effect.type'),
            Forms\Components\ViewField::make('settings.background_treatment_loading')->view('filament.forms.components.hero-view-loading')->viewData(['targetField' => 'background_treatment'])->dehydrated(false)->hiddenLabel()->columnSpanFull()->visible(fn (Get $get): bool => $templateIs($get, 'hero_1')),
            $this->effectSection($page, $templateIs),
            Forms\Components\Select::make('settings.title_decoration')->label($page ? 'تاکید عنوان' : 'Title decoration')->options(['none' => $page ? 'بدون خط' : 'None', 'underline' => $page ? 'خط زیر عنوان' : 'Underline'])->default('none')->visible(fn (Get $get): bool => ! $page && $templateIs($get, 'hero_1')),
            Forms\Components\TextInput::make('settings.height.desktop')->label($page ? 'ارتفاع دسکتاپ' : 'Desktop height')->numeric()->minValue(0)->suffix('px')->visible(fn (Get $get): bool => in_array($get('template'), ['hero_1', 'hero_2'], true)),
            Forms\Components\TextInput::make('settings.height.mobile')->label($page ? 'ارتفاع موبایل' : 'Mobile height')->numeric()->minValue(0)->suffix('px')->visible(fn (Get $get): bool => $templateIs($get, 'hero_1')),
            Forms\Components\TextInput::make('settings.overlay_opacity')->label($page ? 'شفافیت پوشش' : 'Overlay opacity')->numeric()->minValue(0)->maxValue(90)->suffix('%')->default(45)->visible(fn (Get $get): bool => $templateIs($get, 'hero_1') && (! $page || ($get('settings.background_treatment') ?: 'image') === 'image')),

            Forms\Components\Select::make('content.media.kind')->label($page ? 'نوع رسانه' : 'Media kind')->options(['image' => $page ? 'تصویر' : 'Image', 'video' => $page ? 'ویدیو' : 'Video'])->default('image')->live()->visible(fn (Get $get): bool => $templateIs($get, 'hero_2')),
            Forms\Components\Hidden::make('content.media.source_id'),
            Forms\Components\ViewField::make('content.media.url')->label($page ? 'تصویر' : 'Image')->view('filament.forms.components.media-library-url-picker')->viewData(fn (): array => ['images' => self::mediaLibraryImageItems(), 'sourceIdField' => 'source_id'])->visible(fn (Get $get): bool => (! $templateIs($get, 'hero_2') || $get('content.media.kind') !== 'video') && (! $page || ! $templateIs($get, 'hero_1') || ($get('settings.background_treatment') ?: 'image') === 'image'))->columnSpanFull(),
            Forms\Components\TextInput::make('content.media.alt')->label($page ? 'متن جایگزین تصویر' : 'Image alt')->maxLength(255)->visible(fn (Get $get): bool => ! $page || ! $templateIs($get, 'hero_1') || ($get('settings.background_treatment') ?: 'image') === 'image'),
            Forms\Components\ViewField::make('content.media.video_url')->label($page ? 'ویدیوی پس‌زمینه' : 'Background video')->view('filament.forms.components.media-library-video-url-picker')->viewData(fn (): array => ['videos' => self::mediaLibraryVideoItems()])->visible(fn (Get $get): bool => $templateIs($get, 'hero_2') && $get('content.media.kind') === 'video')->columnSpanFull(),
            Forms\Components\Hidden::make('content.media.poster_source_id'),
            Forms\Components\ViewField::make('content.media.poster_url')->label($page ? 'تامبنیل ویدیو' : 'Video thumbnail')->view('filament.forms.components.media-library-url-picker')->viewData(fn (): array => ['images' => self::mediaLibraryImageItems(), 'sourceIdField' => 'poster_source_id'])->visible(fn (Get $get): bool => $templateIs($get, 'hero_2') && $get('content.media.kind') === 'video')->columnSpanFull(),
            $this->responsiveMediaSection($page, $templateIs),

            Forms\Components\TextInput::make('content.selector.placeholder')->label($page ? 'متن پیش‌فرض انتخابگر' : 'Selector placeholder')->maxLength(255)->visible(fn (Get $get): bool => $templateIs($get, 'hero_2')),
            Forms\Components\Repeater::make('content.selector.items')->label($page ? 'گزینه‌های انتخابگر' : 'Selector items')->cloneable()->schema([
                Forms\Components\TextInput::make('label')->label($page ? 'عنوان گزینه' : 'Label')->required()->maxLength(255),
                ActionPicker::make('action')->label($page ? 'مقصد گزینه' : 'Destination')->allowedTypes(self::ACTION_TYPES),
            ])->defaultItems(0)->visible(fn (Get $get): bool => $templateIs($get, 'hero_2'))->columnSpanFull()->columns(2),
            Forms\Components\Repeater::make('content.stats')->label($page ? 'آمار' : 'Stats')->cloneable()->schema([
                Forms\Components\TextInput::make('value')->label($page ? 'مقدار' : 'Value')->required()->maxLength(80),
                Forms\Components\TextInput::make('label')->label($page ? 'عنوان' : 'Label')->required()->maxLength(120),
                Forms\Components\TextInput::make('description')->label($page ? 'توضیحات' : 'Description')->maxLength(160),
                self::iconsaxIconPicker('icon', $page ? 'آیکن' : 'Icon'),
                self::iconsaxIconSizeInput(label: $page ? null : 'Size'),
            ])->defaultItems(0)->visible(fn (Get $get): bool => $templateIs($get, 'hero_3'))->columnSpanFull()->columns(5),
            Forms\Components\Repeater::make('content.social_links')->label($page ? 'لینک‌های پایین هیرو' : 'Bottom links')->cloneable()->schema([
                Forms\Components\TextInput::make('label')->label($page ? 'عنوان' : 'Label')->required()->maxLength(120),
                ActionPicker::make('action')->label($page ? 'مقصد لینک' : 'Destination')->allowedTypes(self::ACTION_TYPES),
                self::iconsaxIconPicker('icon', $page ? 'آیکن' : 'Icon'),
                self::iconsaxIconSizeInput(label: $page ? null : 'Size'),
            ])->defaultItems(0)->visible(fn (Get $get): bool => $templateIs($get, 'hero_1'))->columnSpanFull()->columns(4),
            Forms\Components\TextInput::make('content.scroll_label')->label($page ? 'متن اسکرول' : 'Scroll label')->maxLength(120)->visible(fn (Get $get): bool => $templateIs($get, 'hero_1')),
        ];

        $this->preserveHiddenState($schema);

        return $schema;
    }

    /** @param array<Component> $components */
    private function preserveHiddenState(array $components): void
    {
        foreach ($components as $component) {
            if (! method_exists($component, 'getName') || $component->getName() !== 'settings.background_treatment_loading') {
                $component->dehydratedWhenHidden();
            }

            if (method_exists($component, 'getChildComponents')) {
                $this->preserveHiddenState($component->getChildComponents());
            }
        }
    }

    private function themeSelector(bool $page, \Closure $templateIs): Forms\Components\Select
    {
        return Forms\Components\Select::make('settings.background_treatment')->label($page ? 'نمای هیرو' : 'Hero appearance')->options([
            self::MODE_IMAGE => $page ? 'تصویر تیره (پیش‌فرض)' : 'Image',
            self::MODE_LIGHT_GRID => $page ? 'روشن شبکه‌ای' : 'Light grid',
            self::MODE_DOTS => $page ? 'پس‌زمینه نقطه‌ای متحرک' : 'Animated dotted surface',
            self::MODE_PATHS => $page ? 'مسیرهای متحرک' : 'Animated paths',
        ])->default(self::MODE_IMAGE)->selectablePlaceholder(false)->live()
            ->helperText(fn (Get $get): string => match ($this->appearance($get)) {
                self::MODE_LIGHT_GRID => 'پس‌زمینه روشن شبکه‌ای؛ تنظیم اختصاصی دیگری ندارد.',
                self::MODE_DOTS => 'تنظیمات نقاط متحرک در پنل زیر نمایش داده می‌شود.',
                self::MODE_PATHS => 'تنظیمات مسیرهای متحرک در پنل زیر نمایش داده می‌شود.',
                default => 'تصویر تیره، نمای پیش‌فرض Hero 1 است.',
            })
            ->afterStateUpdated(function (?string $state, Set $set): void {
                $set('settings.background_effect.type', match ($state) {
                    self::MODE_DOTS => 'dotted',
                    self::MODE_PATHS => 'paths',
                    default => 'none',
                });
            })
            ->extraInputAttributes(fn (Forms\Components\Select $component): array => ['wire:loading.attr' => 'disabled', 'wire:target' => $component->getStatePath()])
            ->visible(fn (Get $get): bool => $templateIs($get, 'hero_1'));
    }

    private function effectSection(bool $page, \Closure $templateIs): Forms\Components\Section
    {
        return Forms\Components\Section::make(fn (Get $get): string => $this->appearance($get) === self::MODE_PATHS
            ? ($page ? 'تنظیمات مسیرهای متحرک' : 'Animated paths settings')
            : ($page ? 'تنظیمات پس‌زمینه نقطه‌ای متحرک' : 'Animated dotted surface settings'))->schema([
                Forms\Components\Toggle::make('settings.background_effect.enabled')->label($page ? 'فعال‌سازی' : 'Enabled')->default(true),
                Forms\Components\Toggle::make('settings.background_effect.interactive')->label($page ? 'واکنش به موس' : 'Interactive')->default(true)
                    ->visible(fn (Get $get): bool => $this->appearance($get) === self::MODE_DOTS),
                Forms\Components\Select::make('settings.background_effect.density')->label($page ? 'تراکم' : 'Density')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])->default('medium'),
                Forms\Components\Select::make('settings.background_effect.speed')->label($page ? 'سرعت' : 'Speed')->options(['slow' => 'Slow', 'normal' => 'Normal', 'fast' => 'Fast'])->default('slow'),
                Forms\Components\TextInput::make('settings.background_effect.opacity')
                    ->label(fn (Get $get): string => $page && $this->appearance($get) === self::MODE_PATHS ? 'شفافیت خطوط' : ($page ? 'شفافیت' : 'Opacity'))
                    ->numeric()->minValue(0.05)->maxValue(1)->step(0.05),
                Forms\Components\ColorPicker::make('settings.background_effect.background_color_override')->label($page ? 'رنگ پس‌زمینه' : 'Background color'),
                Forms\Components\ColorPicker::make('settings.background_effect.foreground_color_override')
                    ->label(fn (Get $get): string => $page && $this->appearance($get) === self::MODE_PATHS ? 'رنگ خطوط' : ($page ? 'رنگ نقطه‌ها' : 'Foreground color')),
                Forms\Components\TextInput::make('settings.background_effect.settings.line_width')->label($page ? 'ضخامت خطوط' : 'Line width')->numeric()->minValue(0.2)->maxValue(3)->step(0.1)
                    ->visible(fn (Get $get): bool => $this->appearance($get) === self::MODE_PATHS),
            ])->columns(2)->columnSpanFull()->visible(fn (Get $get): bool => $templateIs($get, 'hero_1') && in_array($this->appearance($get), [self::MODE_DOTS, self::MODE_PATHS], true));
    }

    private function appearance(Get $get): string
    {
        $value = $get('settings.background_treatment');

        return in_array($value, [self::MODE_IMAGE, self::MODE_LIGHT_GRID, self::MODE_DOTS, self::MODE_PATHS], true)
            ? $value
            : self::MODE_IMAGE;
    }

    /** @return array<Component> */
    private function ctaFields(bool $page, \Closure $templateIs): array
    {
        $heroOneEditor = fn (Get $get): bool => $page && $templateIs($get, 'hero_1');
        $legacyEditor = fn (Get $get): bool => ! $heroOneEditor($get);
        $independentPrimaryAction = fn (Get $get): bool => $legacyEditor($get) && ! $templateIs($get, 'hero_2');
        $types = self::ACTION_TYPES;

        return [
            Forms\Components\Toggle::make('content.ctas_enabled')->label('نمایش دکمه‌های هیرو')->default(false)->live()->visible($heroOneEditor),
            Forms\Components\Section::make('دکمه‌های هیرو')->schema([
                Forms\Components\Toggle::make('content.primary_cta.enabled')->label('دکمه اصلی')->default(false)->live(),
                Forms\Components\Grid::make(['default' => 1, 'xl' => 2])->schema([
                    Forms\Components\TextInput::make('content.primary_cta.label')->label('متن دکمه اصلی')->maxLength(255),
                    ActionPicker::make('content.primary_cta.action')->label('مقصد دکمه اصلی')->allowedTypes($types),
                ])->visible(fn (Get $get): bool => (bool) $get('content.primary_cta.enabled')),
                Forms\Components\Toggle::make('content.secondary_cta.enabled')->label('دکمه دوم')->default(false)->live(),
                Forms\Components\Grid::make(['default' => 1, 'xl' => 2])->schema([
                    Forms\Components\TextInput::make('content.secondary_cta.label')->label('متن دکمه دوم')->maxLength(255),
                    ActionPicker::make('content.secondary_cta.action')->label('مقصد دکمه دوم')->allowedTypes($types),
                ])->visible(fn (Get $get): bool => (bool) $get('content.secondary_cta.enabled')),
            ])->visible(fn (Get $get): bool => $heroOneEditor($get) && (bool) $get('content.ctas_enabled'))->columnSpanFull(),
            Forms\Components\TextInput::make('content.primary_cta.label')->label($page ? 'متن دکمه اصلی' : 'Primary button label')->maxLength(255)->visible($legacyEditor),
            ActionPicker::make('content.primary_cta.action')->label($page ? 'مقصد دکمه اصلی' : 'Primary button destination')->allowedTypes($types)->visible($independentPrimaryAction),
            Forms\Components\TextInput::make('content.secondary_cta.label')->label($page ? 'متن دکمه دوم' : 'Secondary button label')->maxLength(255)->visible($legacyEditor),
            ActionPicker::make('content.secondary_cta.action')->label($page ? 'مقصد دکمه دوم' : 'Secondary button destination')->allowedTypes($types)->visible($legacyEditor),
        ];
    }

    private function responsiveMediaSection(bool $page, \Closure $templateIs): Forms\Components\Section
    {
        return Forms\Components\Section::make($page ? 'تنظیمات تصویر' : 'Image settings')->schema([
            ...$this->deviceFields('desktop', $page), ...$this->deviceFields('mobile', $page),
        ])->columns(6)->collapsible()->collapsed()->columnSpanFull()
            ->visible(fn (Get $get): bool => ! $page || ! $templateIs($get, 'hero_1') || ($get('settings.background_treatment') ?: 'image') === 'image');
    }

    /** @return array<Component> */
    private function deviceFields(string $device, bool $page): array
    {
        $prefix = "settings.media.{$device}";

        return [
            Forms\Components\TextInput::make("{$prefix}.width.value")->label(($page ? 'عرض ' : 'Width ').$device)->numeric()->minValue(0)->columnSpan(2),
            Forms\Components\Select::make("{$prefix}.width.unit")->label($page ? 'واحد عرض' : 'Width unit')->options(['%' => '%', 'px' => 'px'])->columnSpan(1),
            Forms\Components\TextInput::make("{$prefix}.height.value")->label(($page ? 'ارتفاع ' : 'Height ').$device)->numeric()->minValue(0)->columnSpan(2),
            Forms\Components\Select::make("{$prefix}.height.unit")->label($page ? 'واحد ارتفاع' : 'Height unit')->options(['%' => '%', 'px' => 'px'])->columnSpan(1),
            Forms\Components\Select::make("{$prefix}.fit")->label($page ? 'واکنش تصویر' : 'Image fit')->options(['normal' => 'Normal', 'cover' => 'Cover', 'contain' => 'Contain'])->default('normal')->columnSpanFull(),
        ];
    }
}
