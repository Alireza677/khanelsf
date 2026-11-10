<?php

namespace App\CMS\Blocks\Product;

use App\Models\ProductAttribute;
use Filament\Forms;

final class ProductSummaryBlock extends AbstractProductBlock
{
    public function key(): string
    {
        return 'product_summary';
    }

    public function label(): string
    {
        return 'معرفی و خرید استاندارد';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-shopping-bag';
    }

    protected function defaultHeadingLevel(): string
    {
        return 'h1';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\Toggle::make('settings.show_share')->label('اشتراک‌گذاری')->default(true),
            Forms\Components\Toggle::make('settings.show_favorites')->label('علاقه‌مندی جلسه مرورگر')->default(true),
            Forms\Components\Select::make('settings.highlight_attribute_ids')
                ->label('ویژگی‌های برجسته')->multiple()->searchable()
                ->options(fn () => ProductAttribute::query()->where('status', 'active')->orderBy('sort_order')->pluck('name', 'id')->all())
                ->helperText('فقط ویژگی‌های انتخاب‌شده و دارای مقدار فعال در محصول نمایش داده می‌شوند.')
                ->default([])->columnSpanFull(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return [];
    }

    protected function normalizeSettings(array $settings): array
    {
        $ids = is_array($settings['highlight_attribute_ids'] ?? null) ? $settings['highlight_attribute_ids'] : [];

        return [
            'show_share' => $this->boolean($settings['show_share'] ?? null, true),
            'show_favorites' => $this->boolean($settings['show_favorites'] ?? null, true),
            'highlight_attribute_ids' => array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => is_scalar($id) && ctype_digit((string) $id) && (int) $id > 0)))),
        ];
    }
}
