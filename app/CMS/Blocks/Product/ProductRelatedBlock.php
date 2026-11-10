<?php

namespace App\CMS\Blocks\Product;

use Filament\Forms;

final class ProductRelatedBlock extends AbstractProductBlock
{
    public function key(): string
    {
        return 'product_related';
    }

    public function label(): string
    {
        return 'محصولات مرتبط';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-rectangle-stack';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\Select::make('settings.variant')->label('نحوه نمایش')
                ->options(['default' => 'نمایش قبلی', 'compact_visual' => 'کارت تصویری فشرده'])->default('default'),
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('محصولات مرتبط')
                ->maxLength(255),
            Forms\Components\TextInput::make('settings.limit')
                ->label('حداکثر تعداد محصولات')
                ->numeric()
                ->minValue(1)
                ->maxValue(6)
                ->default(3)
                ->required(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['title' => $this->stringOrNull($content['title'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'limit' => $this->integerBetween($settings['limit'] ?? null, 1, 6, 3),
            ...(($settings['variant'] ?? null) === 'compact_visual' ? ['variant' => 'compact_visual'] : []),
        ];
    }
}
