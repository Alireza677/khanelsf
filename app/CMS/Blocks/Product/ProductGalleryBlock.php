<?php

namespace App\CMS\Blocks\Product;

use Filament\Forms;

final class ProductGalleryBlock extends AbstractProductBlock
{
    public function key(): string
    {
        return 'product_gallery';
    }

    public function label(): string
    {
        return 'گالری محصول';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-photo';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('گالری محصول')
                ->maxLength(255),
            Forms\Components\Select::make('settings.columns')
                ->label('تعداد ستون‌ها')
                ->options([
                    1 => 'یک ستون',
                    2 => 'دو ستون',
                    3 => 'سه ستون',
                    4 => 'چهار ستون',
                ])
                ->default(3)
                ->required(),
            Forms\Components\Toggle::make('settings.lightbox')
                ->label('نمایش تمام‌صفحه تصاویر')
                ->default(true),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['title' => $this->stringOrNull($content['title'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'columns' => $this->integerBetween($settings['columns'] ?? null, 1, 4, 3),
            'lightbox' => $this->boolean($settings['lightbox'] ?? null, true),
        ];
    }
}
