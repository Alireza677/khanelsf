<?php

namespace App\CMS\Blocks\Form;

use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Support\AbstractBlock;
use App\CMS\Blocks\Support\BlockTemplate;
use App\CMS\Blocks\Support\HeadingLevel;
use App\Filament\Resources\Concerns\UsesMediaLibraryImages;
use App\Models\Form;
use Filament\Forms;
use Filament\Forms\Get;

final class FormBlock extends AbstractBlock implements BlockNormalizer
{
    use UsesMediaLibraryImages;

    public function key(): string
    {
        return 'form';
    }

    public function label(): string
    {
        return 'فرم';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public function templates(): array
    {
        return [
            'default' => new BlockTemplate('default', 'پیش‌فرض', 'partials.blocks.form'),
            'split' => new BlockTemplate('split', 'دو ستونه', 'partials.blocks.form'),
        ];
    }

    public function defaultTemplate(): string
    {
        return 'default';
    }

    public function capabilities(): array
    {
        return ['form_embed'];
    }

    public function filamentSchema(string $context): array
    {
        return [
            Forms\Components\Hidden::make('block_id'),
            Forms\Components\Hidden::make('schema_version')->default($this->version()),
            Forms\Components\Select::make('template')
                ->label('قالب نمایش')
                ->options(['default' => 'پیش‌فرض', 'split' => 'دو ستونه'])
                ->default($this->defaultTemplate())
                ->required()
                ->live(),
            Forms\Components\Select::make('content.form_id')
                ->label('فرم')
                ->options(fn (): array => Form::query()->published()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('content.eyebrow')
                ->label('برچسب بالای عنوان')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('template') === 'split'),
            Forms\Components\TextInput::make('settings.title')
                ->label('عنوان نمایشی')
                ->helperText('اگر خالی باشد، نام فرم نمایش داده می‌شود.')
                ->maxLength(255),
            HeadingLevel::field(),
            Forms\Components\RichEditor::make('settings.description')
                ->label('توضیحات')
                ->columnSpanFull(),
            Forms\Components\Hidden::make('content.media.source_id'),
            Forms\Components\ViewField::make('content.media.url')
                ->label('تصویر اختیاری')
                ->view('filament.forms.components.media-library-url-picker')
                ->viewData(fn (): array => [
                    'images' => self::mediaLibraryImageItems(),
                    'sourceIdField' => 'source_id',
                ])
                ->visible(fn (Get $get): bool => $get('template') === 'split')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('content.media.alt')
                ->label('متن جایگزین تصویر')
                ->maxLength(255)
                ->visible(fn (Get $get): bool => $get('template') === 'split'),
            Forms\Components\Select::make('settings.style')
                ->label('استایل نمایش')
                ->options([
                    'default' => 'پیش‌فرض',
                    'card' => 'کارت',
                ])
                ->default('default')
                ->required()
                ->native(false),
            Forms\Components\Select::make('settings.container')
                ->label('عرض محتوا')
                ->options([
                    'default' => 'پیش‌فرض',
                    'narrow' => 'باریک',
                    'full' => 'تمام عرض',
                ])
                ->default('default')
                ->required()
                ->native(false),
        ];
    }

    public function normalize(array $data): array
    {
        $content = is_array($data['content'] ?? null) ? $data['content'] : [];
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $formId = $content['form_id'] ?? null;

        return [
            'block_id' => $this->stringOrNull($data['block_id'] ?? null),
            'schema_version' => $this->version(),
            'template' => in_array($data['template'] ?? null, ['default', 'split'], true)
                ? $data['template']
                : $this->defaultTemplate(),
            'content' => [
                'form_id' => is_numeric($formId) && (int) $formId > 0 ? (int) $formId : null,
                'eyebrow' => $this->stringOrNull($content['eyebrow'] ?? null),
                'media' => [
                    'source_id' => is_numeric(data_get($content, 'media.source_id'))
                        ? (int) data_get($content, 'media.source_id')
                        : null,
                    'url' => $this->stringOrNull(data_get($content, 'media.url')),
                    'alt' => $this->stringOrNull(data_get($content, 'media.alt')),
                ],
            ],
            'settings' => [
                'title' => $this->stringOrNull($settings['title'] ?? null),
                'heading_tag' => HeadingLevel::normalize($settings['heading_tag'] ?? null),
                'description' => $this->stringOrNull($settings['description'] ?? null),
                'style' => ($settings['style'] ?? null) === 'card' ? 'card' : 'default',
                'container' => in_array($settings['container'] ?? null, ['narrow', 'full'], true)
                    ? $settings['container']
                    : 'default',
            ],
        ];
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
