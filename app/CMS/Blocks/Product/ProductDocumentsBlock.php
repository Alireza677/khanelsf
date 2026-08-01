<?php

namespace App\CMS\Blocks\Product;

use Filament\Forms;

final class ProductDocumentsBlock extends AbstractProductBlock
{
    public function key(): string
    {
        return 'product_documents';
    }

    public function label(): string
    {
        return 'اسناد محصول';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-document-arrow-down';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('اسناد محصول')
                ->maxLength(255),
            Forms\Components\Toggle::make('settings.show_type')
                ->label('نمایش نوع فایل')
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
            'show_type' => $this->boolean($settings['show_type'] ?? null, true),
        ];
    }
}
