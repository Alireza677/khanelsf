<?php

namespace App\CMS\Blocks\Pricing;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\CMS\Blocks\Support\HeadingLevel;
use Filament\Forms;
use Filament\Forms\Get;
use InvalidArgumentException;

final class PricingCardsBlock extends AbstractBlock implements BlockNormalizer
{
    public function __construct(private readonly PricingCardsDataNormalizer $normalizer) {}

    public function key(): string
    {
        return 'pricing_cards';
    }

    public function label(): string
    {
        return 'کارت‌های قیمت‌گذاری';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-banknotes';
    }

    public function version(): int
    {
        return PricingCardsDataNormalizer::SCHEMA_VERSION;
    }

    public function templates(): array
    {
        return ['default' => new BlockTemplate('default', 'پیش‌فرض', 'partials.blocks.pricing_cards')];
    }

    public function defaultTemplate(): string
    {
        return 'default';
    }

    public function capabilities(): array
    {
        return ['static_items', 'item_actions'];
    }

    public function normalize(array $data): array
    {
        return $this->normalizer->normalize($data);
    }

    public function filamentSchema(string $context): array
    {
        if (! in_array($context, [HeroBlock::CONTEXT_PAGE, HeroBlock::CONTEXT_TEMPLATE], true)) {
            throw new InvalidArgumentException("Unsupported Pricing Cards editor context [{$context}].");
        }

        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default($this->version()),
            Forms\Components\Hidden::make('template')->default($this->defaultTemplate()),
            Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->maxLength(255),
            HeadingLevel::field(),
            Forms\Components\RichEditor::make('content.description')->label('توضیحات بخش')->columnSpanFull(),
            Forms\Components\Repeater::make('content.cards')
                ->label('پکیج‌ها')
                ->schema($this->cardFields())
                ->itemLabel(fn (array $state): string => filled($state['title'] ?? null) ? (string) $state['title'] : 'پکیج جدید')
                ->defaultItems(0)
                ->minItems(1)
                ->required()
                ->addActionLabel('افزودن پکیج')
                ->cloneable()
                ->collapsible()
                ->reorderable()
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    private function cardFields(): array
    {
        return [
            Forms\Components\TextInput::make('title')->label('عنوان پکیج')->required()->maxLength(255),
            Forms\Components\TextInput::make('badge')->label('برچسب اختیاری')->maxLength(100),
            Forms\Components\Textarea::make('description')->label('توضیح کوتاه')->rows(3)->maxLength(1000)->columnSpanFull(),
            Forms\Components\Toggle::make('featured')->label('کارت برجسته')->default(false),
            Forms\Components\Toggle::make('show_price')
                ->label('نمایش قیمت')->default(true)->live()
                ->helperText('در صورت غیرفعال بودن، «استعلام قیمت» نمایش داده می‌شود.'),
            Forms\Components\TextInput::make('amount')
                ->label('مبلغ')->numeric()->minValue(0)->step('0.0001')->inputMode('decimal')
                ->rules(['regex:/^\d+(?:\.\d{1,4})?$/'])
                ->required(fn (Get $get): bool => (bool) $get('show_price'))
                ->visible(fn (Get $get): bool => (bool) $get('show_price'))
                ->dehydratedWhenHidden(),
            Forms\Components\TextInput::make('currency')
                ->label('واحد پول')->default('تومان')->maxLength(50)
                ->required(fn (Get $get): bool => (bool) $get('show_price'))
                ->visible(fn (Get $get): bool => (bool) $get('show_price'))
                ->dehydratedWhenHidden(),
            Forms\Components\Select::make('period')
                ->label('دوره قیمت‌گذاری')
                ->options(['monthly' => 'ماهانه', 'one_time' => 'یک‌بار', 'custom' => 'سفارشی'])
                ->default('monthly')->required()->native(false)->live(),
            Forms\Components\TextInput::make('period_label')
                ->label('عنوان دوره سفارشی')->maxLength(100)
                ->required(fn (Get $get): bool => $get('period') === 'custom')
                ->visible(fn (Get $get): bool => $get('period') === 'custom'),
            Forms\Components\TextInput::make('outputs')->label('تعداد یا خلاصه خروجی‌ها')->maxLength(500)->columnSpanFull(),
            Forms\Components\Repeater::make('features')
                ->label('ویژگی‌ها')
                ->schema([Forms\Components\TextInput::make('text')->label('ویژگی')->required()->maxLength(500)])
                ->defaultItems(0)->addActionLabel('افزودن ویژگی')->reorderable()->columnSpanFull(),
            Forms\Components\TextInput::make('button_label')
                ->label('متن دکمه')->maxLength(255)
                ->required(fn (Get $get): bool => filled($get('action.type'))),
            ActionPicker::make('action')->label('مقصد دکمه')->columnSpanFull(),
        ];
    }
}
