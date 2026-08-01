<?php

namespace App\CMS\Blocks\Service;

use Filament\Forms;

final class ServiceHeaderBlock extends AbstractServiceBlock
{
    protected function defaultHeadingLevel(): string
    {
        return 'h1';
    }

    public function key(): string
    {
        return 'service_header';
    }

    public function label(): string
    {
        return 'سربرگ خدمت';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-identification';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\Toggle::make('settings.show_excerpt')->label('نمایش خلاصه')->default(true),
            Forms\Components\Toggle::make('settings.show_image')->label('نمایش تصویر شاخص')->default(true),
            Forms\Components\Select::make('settings.alignment')
                ->label('چیدمان متن')
                ->options(['start' => 'ابتدای سطر', 'center' => 'وسط'])
                ->default('start')->required()->native(false),
            Forms\Components\Select::make('settings.variant')
                ->label('طرح نمایش')
                ->options(['default' => 'استاندارد', 'split' => 'دو ستونه'])
                ->default('default')->required()->native(false),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return [];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'show_excerpt' => $this->boolean($settings['show_excerpt'] ?? null, true),
            'show_image' => $this->boolean($settings['show_image'] ?? null, true),
            'alignment' => ($settings['alignment'] ?? null) === 'center' ? 'center' : 'start',
            'variant' => ($settings['variant'] ?? null) === 'split' ? 'split' : 'default',
        ];
    }
}
