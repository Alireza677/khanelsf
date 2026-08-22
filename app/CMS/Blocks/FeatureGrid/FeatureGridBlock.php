<?php

namespace App\CMS\Blocks\FeatureGrid;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\CMS\Blocks\Support\HeadingLevel;
use App\Filament\Resources\Concerns\UsesIconsaxIconPicker;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Models\Post;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Get;
use Filament\Forms\Set;
use InvalidArgumentException;

final class FeatureGridBlock extends AbstractBlock implements BlockNormalizer
{
    use UsesIconsaxIconPicker;
    use UsesMediaLibraryImages;

    public function __construct(
        private readonly FeatureGridDataNormalizer $normalizer,
    ) {}

    public function key(): string
    {
        return 'feature_grid';
    }

    public function label(): string
    {
        return 'شبکه ویژگی‌ها';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-squares-2x2';
    }

    public function version(): int
    {
        return FeatureGridDataNormalizer::SCHEMA_VERSION;
    }

    public function templates(): array
    {
        return [
            'default' => new BlockTemplate(
                'default',
                'پیش‌فرض',
                'partials.blocks.feature_grid',
            ),
        ];
    }

    public function defaultTemplate(): string
    {
        return 'default';
    }

    public function capabilities(): array
    {
        return ['static_items', 'dynamic_items', 'item_actions'];
    }

    public function filamentSchema(string $context): array
    {
        $this->guardContext($context);
        $page = $context === HeroBlock::CONTEXT_PAGE;

        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default($this->version()),
            Forms\Components\Hidden::make('template')->default($this->defaultTemplate()),
            Forms\Components\Select::make('settings.section_background')
                ->label($page ? 'پس‌زمینه بخش' : 'Section background')
                ->options($page
                    ? ['default' => 'پیش‌فرض', 'muted' => 'ملایم', 'dark' => 'تیره']
                    : ['default' => 'Default', 'muted' => 'Muted', 'dark' => 'Dark'])
                ->default('default'),
            Forms\Components\Select::make('settings.alignment')
                ->label($page ? 'چیدمان' : 'Alignment')
                ->options($page
                    ? ['left' => 'چپ', 'center' => 'وسط']
                    : ['left' => 'Left', 'center' => 'Center'])
                ->default('center'),
            Forms\Components\TextInput::make('settings.eyebrow')
                ->label($page ? 'برچسب بالای عنوان' : 'Eyebrow')
                ->maxLength(255),
            Forms\Components\TextInput::make('content.section_title')
                ->label($page ? 'عنوان بخش' : 'Section title')
                ->required()
                ->maxLength(255),
            HeadingLevel::field('settings.heading_tag', $page ? 'تگ عنوان' : 'Heading tag'),
            Forms\Components\RichEditor::make('content.section_description')
                ->label($page ? 'توضیحات بخش' : 'Section description')
                ->columnSpanFull(),
            Forms\Components\Select::make('content.items_mode')
                ->label($page ? 'نوع آیتم‌ها' : 'Items mode')
                ->options($page
                    ? ['static' => 'ثابت', 'dynamic' => 'داینامیک']
                    : ['static' => 'Static', 'dynamic' => 'Dynamic'])
                ->default('static')
                ->live()
                ->required(),
            ...$this->dynamicFields($page),
            Forms\Components\Repeater::make('content.items')
                ->label($page ? 'آیتم‌ها' : 'Items')
                ->cloneable()
                ->itemLabel(fn (array $state): ?string => $state['title'] ?? ($page ? 'آیتم' : 'Item'))
                ->schema($this->itemFields($page))
                ->columns(3)
                ->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->reorderable()
                ->visible(fn (Get $get): bool => ($get('content.items_mode') ?? 'static') === 'static'),
        ];
    }

    public function normalize(array $data): array
    {
        return $this->normalizer->normalize($data);
    }

    /** @return array<Component> */
    private function dynamicFields(bool $page): array
    {
        $visible = fn (Get $get): bool => $get('content.items_mode') === 'dynamic';

        return [
            Forms\Components\Select::make('content.dynamic_source')
                ->label($page ? 'منبع داینامیک' : 'Dynamic source')
                ->options($page
                    ? ['posts' => 'آخرین نوشته‌ها', 'projects' => 'آخرین پروژه‌ها']
                    : ['posts' => 'Latest posts', 'projects' => 'Latest projects'])
                ->default('posts')
                ->live()
                ->afterStateUpdated(
                    fn (Set $set) => $set('dynamic_button_overrides', []),
                )
                ->required()
                ->visible($visible),
            Forms\Components\TextInput::make('settings.dynamic_rows')
                ->label($page ? 'تعداد ردیف' : 'Rows')
                ->numeric()->minValue(1)->maxValue(6)->default(1)
                ->required()->visible($visible),
            Forms\Components\TextInput::make('settings.dynamic_columns')
                ->label($page ? 'تعداد ستون درخواستی' : 'Requested columns')
                ->numeric()->minValue(1)->maxValue(12)->default(3)
                ->required()->visible($visible),
            Forms\Components\TextInput::make('settings.dynamic_grid_width')
                ->label($page ? 'عرض شبکه' : 'Grid width')
                ->numeric()->minValue(240)->maxValue(2400)->default(1180)
                ->suffix('px')->required()->visible($visible),
            Forms\Components\TextInput::make('settings.dynamic_item_width')
                ->label($page ? 'حداقل عرض هر آیتم' : 'Minimum item width')
                ->numeric()->minValue(120)->maxValue(800)->default(280)
                ->suffix('px')->required()->visible($visible),
            Forms\Components\TextInput::make('content.dynamic_button_label')
                ->label($page ? 'متن پیش‌فرض دکمه' : 'Default button label')
                ->default('مشاهده بیشتر')
                ->maxLength(255)
                ->visible($visible),
            Forms\Components\Repeater::make('content.dynamic_button_overrides')
                ->label($page ? 'متن دکمه اختصاصی' : 'Button label overrides')
                ->cloneable()
                ->schema([
                    Forms\Components\Select::make('record_id')
                        ->label($page ? 'نوشته / پروژه' : 'Post / Project')
                        ->options(fn (Get $get): array => $get('../../dynamic_source') === 'projects'
                            ? Project::query()->published()->latest('published_at')->pluck('title', 'id')->all()
                            : Post::query()->published()->latest('published_at')->pluck('title', 'id')->all())
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('button_label')
                        ->label($page ? 'متن دکمه' : 'Button label')
                        ->required()
                        ->maxLength(255),
                ])
                ->defaultItems(0)
                ->columns(2)
                ->columnSpanFull()
                ->collapsible()
                ->visible($visible),
        ];
    }

    /** @return array<Component> */
    private function itemFields(bool $page): array
    {
        return [
            Forms\Components\TextInput::make('title')
                ->label($page ? 'عنوان' : 'Title')
                ->required()
                ->maxLength(255),
            self::iconsaxIconPicker('icon', $page ? 'آیکن' : 'Icon'),
            self::iconsaxIconSizeInput(label: $page ? 'اندازه آیکن' : 'Icon size'),
            Forms\Components\ViewField::make('image')
                ->label($page ? 'تصویر' : 'Image')
                ->view('filament.forms.components.media-library-url-picker')
                ->viewData(fn (): array => ['images' => self::mediaLibraryImageItems()])
                ->helperText($page
                    ? 'تصویر اختیاری برای این ویژگی.'
                    : 'Optional image for this feature.')
                ->columnSpanFull(),
            ...$this->imageSettingsFields($page),
            Forms\Components\RichEditor::make('description')
                ->label($page ? 'توضیحات' : 'Description')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('button_label')
                ->label($page ? 'متن دکمه' : 'Button label')
                ->maxLength(255)
                ->required(fn (Get $get): bool => filled($get('action.type')))
                ->validationMessages([
                    'required' => 'برای مقصد دکمه، متن دکمه را وارد کنید.',
                ]),
            ActionPicker::make('action')
                ->label($page ? 'مقصد دکمه' : 'Button destination')
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
                ->columnSpanFull(),
        ];
    }

    /** @return array<Component> */
    private function imageSettingsFields(bool $page): array
    {
        return [
            Forms\Components\Section::make($page ? 'تنظیمات تصویر' : 'Image settings')
                ->schema([
                    Forms\Components\Grid::make(['default' => 1, 'xl' => 2])
                        ->schema([
                            Forms\Components\Section::make($page ? 'دسکتاپ' : 'Desktop')
                                ->schema($this->imageDeviceFields('image', $page))
                                ->columns(6),
                            Forms\Components\Section::make($page ? 'موبایل' : 'Mobile')
                                ->schema($this->imageDeviceFields('image_mobile', $page))
                                ->columns(6),
                        ]),
                ])
                ->collapsible()
                ->collapsed()
                ->columnSpanFull(),
        ];
    }

    /** @return array<Component> */
    private function imageDeviceFields(string $prefix, bool $page): array
    {
        return [
            Forms\Components\TextInput::make("{$prefix}_width_value")
                ->label($page ? 'عرض' : 'Width')->numeric()->minValue(0)->columnSpan(2),
            Forms\Components\Select::make("{$prefix}_width_unit")
                ->label($page ? 'واحد عرض' : 'Width unit')
                ->options(['%' => '%', 'px' => 'px'])->default('%'),
            Forms\Components\TextInput::make("{$prefix}_height_value")
                ->label($page ? 'ارتفاع' : 'Height')->numeric()->minValue(0)->columnSpan(2),
            Forms\Components\Select::make("{$prefix}_height_unit")
                ->label($page ? 'واحد ارتفاع' : 'Height unit')
                ->options(['%' => '%', 'px' => 'px'])->default('px'),
            Forms\Components\Select::make("{$prefix}_fit")
                ->label($page ? 'واکنش تصویر' : 'Image fit')
                ->options($page
                    ? ['normal' => 'عادی', 'cover' => 'پوشش', 'contain' => 'کامل دیده شود']
                    : ['normal' => 'Normal', 'cover' => 'Cover', 'contain' => 'Contain'])
                ->default('normal')
                ->columnSpanFull(),
        ];
    }

    private function guardContext(string $context): void
    {
        if (! in_array($context, [HeroBlock::CONTEXT_PAGE, HeroBlock::CONTEXT_TEMPLATE], true)) {
            throw new InvalidArgumentException("Unsupported Feature Grid editor context [{$context}].");
        }
    }
}
