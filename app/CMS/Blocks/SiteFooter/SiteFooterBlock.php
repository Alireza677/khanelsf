<?php

namespace App\CMS\Blocks\SiteFooter;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Models\Menu;
use Filament\Forms;
use InvalidArgumentException;

final class SiteFooterBlock extends AbstractBlock implements BlockNormalizer
{
    use UsesMediaLibraryImages;

    private const ACTION_TYPES = [
        'custom_url',
        'page',
        'project',
        'product',
        'service',
        'anchor',
    ];

    public function __construct(
        private readonly SiteFooterDataNormalizer $normalizer,
    ) {}

    public function key(): string
    {
        return 'site_footer';
    }

    public function label(): string
    {
        return 'فوتر سازمانی تیره';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-rectangle-group';
    }

    public function version(): int
    {
        return SiteFooterDataNormalizer::SCHEMA_VERSION;
    }

    public function templates(): array
    {
        return [
            'corporate-dark-v1' => new BlockTemplate(
                'corporate-dark-v1',
                'فوتر سازمانی تیره',
                'partials.blocks.site-footer-corporate-dark',
            ),
        ];
    }

    public function defaultTemplate(): string
    {
        return 'corporate-dark-v1';
    }

    public function capabilities(): array
    {
        return ['site_footer_context', 'navigation', 'shared_media', 'interactive_actions'];
    }

    public function filamentSchema(string $context): array
    {
        if ($context !== HeroBlock::CONTEXT_TEMPLATE) {
            throw new InvalidArgumentException('Site Footer is only available in the Template editor.');
        }

        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default($this->version()),
            Forms\Components\Hidden::make('template')->default($this->defaultTemplate()),
            Forms\Components\Repeater::make('content.menu_columns')
                ->label('ستون‌های منو')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان ستون')
                        ->maxLength(255),
                    Forms\Components\Select::make('menu_id')
                        ->label('منو')
                        ->options(fn (): array => Menu::query()
                            ->where('status', 'active')
                            ->orderBy('title')
                            ->pluck('title', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->native(false),
                ])
                ->defaultItems(3)
                ->minItems(3)
                ->maxItems(3)
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('content.contact_title')
                ->label('عنوان ستون تماس')
                ->default('ارتباط با ما')
                ->maxLength(255),
            Forms\Components\TextInput::make('content.about_title')
                ->label('عنوان درباره ما')
                ->default('درباره ما')
                ->maxLength(255),
            Forms\Components\Textarea::make('content.about_text')
                ->label('متن درباره ما')
                ->helperText('اگر خالی باشد، متن فوتر از تنظیمات سایت استفاده می‌شود.')
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('content.badges_title')
                ->label('عنوان بخش مجوزها')
                ->default('مجوزها و نمادهای اعتماد')
                ->maxLength(255),
            Forms\Components\Repeater::make('content.badges')
                ->label('مجوزها و نمادهای اعتماد')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->label('عنوان')
                        ->maxLength(255),
                    Forms\Components\ViewField::make('media_id')
                        ->label('تصویر')
                        ->view('filament.forms.components.media-library-picker')
                        ->viewData(fn (): array => ['images' => self::mediaLibraryImageItems()])
                        ->required()
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('url')
                        ->label('لینک اختیاری')
                        ->maxLength(2048),
                    Forms\Components\TextInput::make('alt')
                        ->label('متن جایگزین اختیاری')
                        ->helperText('اگر خالی باشد از Alt/Title رسانه استفاده می‌شود.')
                        ->maxLength(255),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('ترتیب')
                        ->numeric()
                        ->default(0),
                    Forms\Components\Toggle::make('enabled')
                        ->label('فعال')
                        ->default(true),
                ])
                ->columns(2)
                ->collapsible()
                ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? (string) $state['title'] : 'نماد جدید')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('content.privacy_label')
                ->label('متن لینک حریم خصوصی')
                ->default('حریم خصوصی')
                ->maxLength(255),
            ActionPicker::make('content.privacy_action')
                ->label('مقصد حریم خصوصی')
                ->allowedTypes(self::ACTION_TYPES)
                ->columnSpanFull(),
            Forms\Components\TextInput::make('content.terms_label')
                ->label('متن لینک قوانین')
                ->default('قوانین و مقررات')
                ->maxLength(255),
            ActionPicker::make('content.terms_action')
                ->label('مقصد قوانین و مقررات')
                ->allowedTypes(self::ACTION_TYPES)
                ->columnSpanFull(),
        ];
    }

    public function normalize(array $data): array
    {
        return $this->normalizer->normalize($data);
    }
}
