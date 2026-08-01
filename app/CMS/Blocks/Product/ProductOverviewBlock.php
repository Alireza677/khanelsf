<?php

namespace App\CMS\Blocks\Product;

use Filament\Forms;

final class ProductOverviewBlock extends AbstractProductBlock
{
    public function key(): string
    {
        return 'product_overview';
    }

    public function label(): string
    {
        return 'معرفی محصول';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-document-text';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('معرفی محصول')
                ->maxLength(255),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['title' => $this->stringOrNull($content['title'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [];
    }
}
