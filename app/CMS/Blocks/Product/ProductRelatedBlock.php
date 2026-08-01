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
        ];
    }
}
