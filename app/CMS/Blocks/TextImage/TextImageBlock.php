<?php

namespace App\CMS\Blocks\TextImage;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Models\Template;
use Filament\Forms;
use InvalidArgumentException;

final class TextImageBlock extends AbstractBlock implements BlockNormalizer
{
    use UsesMediaLibraryImages;

    public function __construct(private readonly TextImageDataNormalizer $normalizer) {}

    public function key(): string
    {
        return 'text_image';
    }

    public function label(): string
    {
        return 'متن و تصویر';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-rectangle-group';
    }

    public function templateTargets(): ?array
    {
        return array_values(array_diff(array_keys(Template::TYPES), ['site_header', 'site_footer']));
    }

    public function templates(): array
    {
        return ['default' => new BlockTemplate('default', 'پیش‌فرض', 'partials.blocks.text_image')];
    }

    public function defaultTemplate(): string
    {
        return 'default';
    }

    public function capabilities(): array
    {
        return ['rich_text', 'media_reference', 'primary_action'];
    }

    public function normalize(array $data): array
    {
        return $this->normalizer->normalize($data);
    }

    public function filamentSchema(string $context): array
    {
        if (! in_array($context, [HeroBlock::CONTEXT_PAGE, HeroBlock::CONTEXT_TEMPLATE], true)) {
            throw new InvalidArgumentException("Unsupported Text & Image editor context [{$context}].");
        }

        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default($this->version()),
            Forms\Components\Hidden::make('template')->default($this->defaultTemplate()),
            Forms\Components\Section::make('عنوان بخش')->schema([
                Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->maxLength(255),
                Forms\Components\TextInput::make('content.eyebrow')->label('عنوان کوچک')->maxLength(255),
                Forms\Components\Select::make('settings.heading_tag')->label('تگ عنوان')
                    ->options(['h2' => 'H2', 'h3' => 'H3', 'h4' => 'H4'])->default('h2')->native(false),
            ])->columns(2)->columnSpanFull(),
            Forms\Components\RichEditor::make('content.description')
                ->label('متن توضیحات')
                ->toolbarButtons(['bold', 'italic', 'link', 'bulletList', 'orderedList', 'undo', 'redo'])
                ->extraInputAttributes(['dir' => 'rtl'])
                ->columnSpanFull(),
            Forms\Components\ViewField::make('content.media_id')
                ->label('تصویر')
                ->view('filament.forms.components.media-library-picker')
                ->viewData(fn (): array => ['images' => self::mediaLibraryImageItems()])
                ->columnSpanFull(),
            Forms\Components\TextInput::make('content.button.label')->label('عنوان دکمه')->maxLength(255),
            ActionPicker::make('content.button.action')->label('مقصد دکمه')->columnSpanFull(),
            Forms\Components\Select::make('settings.image_position')->label('موقعیت تصویر')
                ->options(['left' => 'تصویر سمت چپ', 'right' => 'تصویر سمت راست'])->default('left'),
            Forms\Components\Select::make('settings.column_ratio')->label('نسبت ستون‌ها')
                ->options(['equal' => 'مساوی (۵۰ / ۵۰)', 'text_wide' => 'متن بزرگ‌تر (۶۰ / ۴۰)', 'image_wide' => 'تصویر بزرگ‌تر (۴۰ / ۶۰)'])
                ->default('equal'),
            Forms\Components\Select::make('settings.vertical_alignment')->label('تراز عمودی محتوا')
                ->options(['top' => 'بالا', 'center' => 'وسط', 'bottom' => 'پایین'])->default('center'),
            Forms\Components\Select::make('settings.image_ratio')->label('نسبت تصویر')
                ->options(['natural' => 'طبیعی', '4:3' => '۴:۳', '16:9' => '۱۶:۹', '1:1' => 'مربع ۱:۱'])->default('natural'),
            Forms\Components\Select::make('settings.section_background')->label('پس‌زمینه بخش')
                ->options(['default' => 'پیش‌فرض', 'muted' => 'ملایم', 'dark' => 'تیره'])->default('default'),
        ];
    }
}
