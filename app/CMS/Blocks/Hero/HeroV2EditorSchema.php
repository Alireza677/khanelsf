<?php

namespace App\CMS\Blocks\Hero;

use App\CMS\Blocks\Support\BlockTemplate;
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

            Forms\Components\TextInput::make('content.eyebrow.text')->label($page ? 'برچسب بالای عنوان' : 'Eyebrow')->maxLength(255)->visible(fn (Get $get): bool => in_array($get('template'), ['hero_1', 'hero_3'], true)),
            self::iconsaxIconPicker('content.eyebrow.icon', $page ? 'آیکن برچسب' : 'Eyebrow icon')->visible(fn (Get $get): bool => in_array($get('template'), ['hero_1', 'hero_3'], true)),
            self::iconsaxIconSizeInput('settings.eyebrow_icon_size', $page ? null : 'Size')->visible(fn (Get $get): bool => in_array($get('template'), ['hero_1', 'hero_3'], true)),
            Forms\Components\TextInput::make('content.title')->label($page ? 'عنوان' : 'Title')->required()->maxLength(255),
            Forms\Components\TextInput::make('content.title_secondary')->label($page ? 'خط دوم عنوان' : 'Title second line')->maxLength(255)->visible(fn (Get $get): bool => $templateIs($get, 'hero_1')),
            Forms\Components\TextInput::make('content.lead')->label($page ? 'زیرعنوان' : 'Lead')->maxLength(255),
            Forms\Components\Textarea::make('content.description')->label($page ? 'توضیحات' : 'Description')->rows($page ? 4 : 3)->columnSpanFull(),
            Forms\Components\Select::make('settings.heading_tag')->label($page ? 'تگ عنوان' : 'Heading tag')->options(['h1' => 'H1', 'h2' => 'H2'])->default('h2')->native(false),
            Forms\Components\Select::make('settings.alignment')->label($page ? 'چیدمان' : 'Alignment')->options(['left' => $page ? 'چپ' : 'Left', 'right' => $page ? 'راست' : 'Right', 'center' => $page ? 'وسط' : 'Center', 'start' => 'Start', 'end' => 'End'])->default('left'),
            Forms\Components\Select::make('settings.color_mode')->label($page ? 'حالت رنگ' : 'Color mode')->options(['default' => $page ? 'پیش‌فرض' : 'Default', 'muted' => $page ? 'ملایم' : 'Muted', 'dark' => $page ? 'تیره' : 'Dark'])->default('default'),

            Forms\Components\TextInput::make('content.primary_cta.label')->label($page ? 'متن دکمه اصلی' : 'Primary button label')->maxLength(255),
            Forms\Components\TextInput::make('content.primary_cta.url')->label($page ? 'لینک دکمه اصلی' : 'Primary button URL')->maxLength(255),
            Forms\Components\TextInput::make('content.secondary_cta.label')->label($page ? 'متن دکمه دوم' : 'Secondary button label')->maxLength(255),
            Forms\Components\TextInput::make('content.secondary_cta.url')->label($page ? 'لینک دکمه دوم' : 'Secondary button URL')->maxLength(255),

            $this->themeSelector($page, $templateIs),
            Forms\Components\Hidden::make('settings.background_effect.type'),
            Forms\Components\ViewField::make('settings.background_treatment_loading')->view('filament.forms.components.hero-view-loading')->viewData(['targetField' => 'background_treatment'])->dehydrated(false)->hiddenLabel()->columnSpanFull()->visible(fn (Get $get): bool => $templateIs($get, 'hero_1')),
            $this->dottedSection($page, $templateIs),
            $this->pathsSection($page, $templateIs),
            Forms\Components\Select::make('settings.title_decoration')->label($page ? 'تاکید عنوان' : 'Title decoration')->options(['none' => $page ? 'بدون خط' : 'None', 'underline' => $page ? 'خط زیر عنوان' : 'Underline'])->default('none')->visible(fn (Get $get): bool => $templateIs($get, 'hero_1')),
            Forms\Components\TextInput::make('settings.height.desktop')->label($page ? 'ارتفاع دسکتاپ' : 'Desktop height')->numeric()->minValue(0)->suffix('px')->visible(fn (Get $get): bool => in_array($get('template'), ['hero_1', 'hero_2'], true)),
            Forms\Components\TextInput::make('settings.height.mobile')->label($page ? 'ارتفاع موبایل' : 'Mobile height')->numeric()->minValue(0)->suffix('px')->visible(fn (Get $get): bool => $templateIs($get, 'hero_1')),
            Forms\Components\TextInput::make('settings.overlay_opacity')->label($page ? 'شفافیت پوشش' : 'Overlay opacity')->numeric()->minValue(0)->maxValue(90)->suffix('%')->default(45)->visible(fn (Get $get): bool => $templateIs($get, 'hero_1')),

            Forms\Components\Select::make('content.media.kind')->label($page ? 'نوع رسانه' : 'Media kind')->options(['image' => $page ? 'تصویر' : 'Image', 'video' => $page ? 'ویدیو' : 'Video'])->default('image')->live()->visible(fn (Get $get): bool => $templateIs($get, 'hero_2')),
            Forms\Components\Hidden::make('content.media.source_id'),
            Forms\Components\ViewField::make('content.media.url')->label($page ? 'تصویر' : 'Image')->view('filament.forms.components.media-library-url-picker')->viewData(fn (): array => ['images' => self::mediaLibraryImageItems(), 'sourceIdField' => 'source_id'])->visible(fn (Get $get): bool => ! $templateIs($get, 'hero_2') || $get('content.media.kind') !== 'video')->columnSpanFull(),
            Forms\Components\TextInput::make('content.media.alt')->label($page ? 'متن جایگزین تصویر' : 'Image alt')->maxLength(255),
            Forms\Components\ViewField::make('content.media.video_url')->label($page ? 'ویدیوی پس‌زمینه' : 'Background video')->view('filament.forms.components.media-library-video-url-picker')->viewData(fn (): array => ['videos' => self::mediaLibraryVideoItems()])->visible(fn (Get $get): bool => $templateIs($get, 'hero_2') && $get('content.media.kind') === 'video')->columnSpanFull(),
            Forms\Components\Hidden::make('content.media.poster_source_id'),
            Forms\Components\ViewField::make('content.media.poster_url')->label($page ? 'تامبنیل ویدیو' : 'Video thumbnail')->view('filament.forms.components.media-library-url-picker')->viewData(fn (): array => ['images' => self::mediaLibraryImageItems(), 'sourceIdField' => 'poster_source_id'])->visible(fn (Get $get): bool => $templateIs($get, 'hero_2') && $get('content.media.kind') === 'video')->columnSpanFull(),
            $this->responsiveMediaSection($page),

            Forms\Components\TextInput::make('content.selector.placeholder')->label($page ? 'متن پیش‌فرض انتخابگر' : 'Selector placeholder')->maxLength(255)->visible(fn (Get $get): bool => $templateIs($get, 'hero_2')),
            Forms\Components\Repeater::make('content.selector.items')->label($page ? 'گزینه‌های انتخابگر' : 'Selector items')->cloneable()->schema([
                Forms\Components\TextInput::make('label')->label($page ? 'عنوان گزینه' : 'Label')->required()->maxLength(255),
                Forms\Components\TextInput::make('url')->label($page ? 'لینک' : 'URL')->required()->maxLength(255),
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
                Forms\Components\TextInput::make('url')->label($page ? 'لینک' : 'URL')->required()->maxLength(255),
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
            'image' => $page ? 'تصویر تیره' : 'Image', 'light_grid' => $page ? 'روشن شبکه‌ای' : 'Light grid',
            'animated_dotted_surface' => $page ? 'پس‌زمینه نقطه‌ای متحرک' : 'Animated dotted surface',
            'animated_paths' => $page ? 'مسیرهای متحرک' : 'Animated paths', 'video' => $page ? 'ویدیو' : 'Video',
        ])->default('image')->live()->afterStateUpdated(function (?string $state, Set $set, $livewire): void {
            $set('settings.background_effect.type', match ($state) {
                'animated_dotted_surface' => 'dotted',
                'animated_paths' => 'paths',
                default => 'none',
            });
            $livewire->skipRender();
        })
            ->extraInputAttributes(fn (Forms\Components\Select $component): array => ['wire:loading.attr' => 'disabled', 'wire:target' => $component->getStatePath()])
            ->visible(fn (Get $get): bool => $templateIs($get, 'hero_1'));
    }

    private function dottedSection(bool $page, \Closure $templateIs): Forms\Components\Section
    {
        return Forms\Components\Section::make($page ? 'تنظیمات پس‌زمینه متحرک' : 'Animated background settings')->schema([
            Forms\Components\Toggle::make('settings.background_effect.enabled')->label($page ? 'فعال‌سازی' : 'Enabled')->default(true),
            Forms\Components\Toggle::make('settings.background_effect.interactive')->label($page ? 'واکنش به موس' : 'Interactive')->default(true),
            Forms\Components\Select::make('settings.background_effect.density')->label($page ? 'تراکم' : 'Density')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])->default('medium'),
            Forms\Components\Select::make('settings.background_effect.speed')->label($page ? 'سرعت' : 'Speed')->options(['slow' => 'Slow', 'normal' => 'Normal', 'fast' => 'Fast'])->default('slow'),
            Forms\Components\TextInput::make('settings.background_effect.opacity')->label($page ? 'شفافیت' : 'Opacity')->numeric()->minValue(0.1)->maxValue(1)->step(0.05),
            Forms\Components\ColorPicker::make('settings.background_effect.background_color_override')->label($page ? 'رنگ پس‌زمینه' : 'Background color'),
            Forms\Components\ColorPicker::make('settings.background_effect.foreground_color_override')->label($page ? 'رنگ نقطه‌ها' : 'Dot color'),
        ])->columns(2)->columnSpanFull()->visible(fn (Get $get): bool => $templateIs($get, 'hero_1'))
            ->extraAttributes(fn (Forms\Components\Section $component): array => $this->themeAttributes($component, 'animated_dotted_surface'));
    }

    private function pathsSection(bool $page, \Closure $templateIs): Forms\Components\Section
    {
        return Forms\Components\Section::make($page ? 'تنظیمات مسیرهای متحرک' : 'Animated paths settings')->schema([
            Forms\Components\ColorPicker::make('settings.background_effect.background_color_override')->label($page ? 'رنگ پس‌زمینه' : 'Background color'),
            Forms\Components\ColorPicker::make('settings.background_effect.foreground_color_override')->label($page ? 'رنگ خطوط' : 'Line color'),
            Forms\Components\TextInput::make('settings.background_effect.opacity')->label($page ? 'شفافیت خطوط' : 'Line opacity')->numeric()->minValue(0.05)->maxValue(1)->step(0.05),
            Forms\Components\Select::make('settings.background_effect.speed')->label($page ? 'سرعت' : 'Speed')->options(['slow' => 'Slow', 'normal' => 'Normal', 'fast' => 'Fast'])->default('normal'),
            Forms\Components\Select::make('settings.background_effect.density')->label($page ? 'تراکم' : 'Density')->options(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'])->default('medium'),
            Forms\Components\TextInput::make('settings.background_effect.settings.line_width')->label($page ? 'ضخامت خطوط' : 'Line width')->numeric()->minValue(0.2)->maxValue(3)->step(0.1),
            Forms\Components\Toggle::make('settings.background_effect.enabled')->label($page ? 'فعال‌سازی حرکت' : 'Enable animation')->default(true),
        ])->columns(2)->columnSpanFull()->visible(fn (Get $get): bool => $templateIs($get, 'hero_1'))
            ->extraAttributes(fn (Forms\Components\Section $component): array => $this->themeAttributes($component, 'animated_paths'));
    }

    private function responsiveMediaSection(bool $page): Forms\Components\Section
    {
        return Forms\Components\Section::make($page ? 'تنظیمات تصویر' : 'Image settings')->schema([
            ...$this->deviceFields('desktop', $page), ...$this->deviceFields('mobile', $page),
        ])->columns(6)->collapsible()->collapsed()->columnSpanFull();
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

    private function themeAttributes(Forms\Components\Section $component, string $theme): array
    {
        $statePath = str($component->getStatePath())->beforeLast('.')->append('.settings.background_treatment')->toString();

        return ['x-data' => "{ heroTheme: \$wire.entangle('{$statePath}') }", 'x-show' => "heroTheme === '{$theme}'", 'x-cloak' => true];
    }
}
