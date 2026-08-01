<?php

namespace App\CMS\Blocks\Product;

use Filament\Forms;

final class ProductHeaderBlock extends AbstractProductBlock
{
    protected function defaultHeadingLevel(): string
    {
        return 'h1';
    }

    public function key(): string
    {
        return 'product_header';
    }

    public function label(): string
    {
        return 'سربرگ محصول';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-shopping-bag';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.eyebrow')
                ->label('برچسب بالای عنوان')
                ->maxLength(120),
            ...collect([
                'show_image' => 'تصویر شاخص',
                'show_category' => 'دسته‌بندی',
                'show_price' => 'قیمت',
                'show_availability' => 'وضعیت موجودی',
                'show_cta' => 'دکمه افزودن به سبد خرید',
            ])->map(fn (string $label, string $name) => Forms\Components\Toggle::make("settings.{$name}")
                ->label("نمایش {$label}")
                ->default(true))->all(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['eyebrow' => $this->stringOrNull($content['eyebrow'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'show_image' => $this->boolean($settings['show_image'] ?? null, true),
            'show_category' => $this->boolean($settings['show_category'] ?? null, true),
            'show_price' => $this->boolean($settings['show_price'] ?? null, true),
            'show_availability' => $this->boolean($settings['show_availability'] ?? null, true),
            'show_cta' => $this->boolean($settings['show_cta'] ?? null, false),
        ];
    }
}
