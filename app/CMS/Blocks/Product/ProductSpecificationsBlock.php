<?php

namespace App\CMS\Blocks\Product;

use Filament\Forms;

final class ProductSpecificationsBlock extends AbstractProductBlock
{
    public function key(): string
    {
        return 'product_specifications';
    }

    public function label(): string
    {
        return 'مشخصات محصول';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-list-bullet';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('مشخصات محصول')
                ->maxLength(255),
            Forms\Components\Select::make('settings.layout')
                ->label('چیدمان')
                ->options([
                    'table' => 'جدولی',
                    'stacked' => 'فهرستی',
                ])
                ->default('table')
                ->required(),
            Forms\Components\Toggle::make('settings.show_group')
                ->label('نمایش گروه مشخصات')
                ->default(true),
            Forms\Components\Toggle::make('settings.show_unit')
                ->label('نمایش واحد')
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
            'layout' => in_array($settings['layout'] ?? null, ['table', 'stacked'], true)
                ? $settings['layout']
                : 'table',
            'show_group' => $this->boolean($settings['show_group'] ?? null, true),
            'show_unit' => $this->boolean($settings['show_unit'] ?? null, true),
        ];
    }
}
