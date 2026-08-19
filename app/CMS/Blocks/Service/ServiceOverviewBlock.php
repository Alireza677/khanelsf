<?php

namespace App\CMS\Blocks\Service;

use Filament\Forms;

final class ServiceOverviewBlock extends AbstractServiceBlock
{
    public function key(): string
    {
        return 'service_overview';
    }

    public function label(): string
    {
        return 'معرفی خدمت';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->default('معرفی خدمت')->maxLength(255),
            Forms\Components\Select::make('settings.width')
                ->label('عرض محتوا')->options(['default' => 'استاندارد', 'narrow' => 'باریک'])
                ->default('default')->required()->native(false),
            Forms\Components\Select::make('settings.variant')
                ->label('سبک نمایش')->options(['default' => 'استاندارد', 'professional' => 'حرفه‌ای'])
                ->default('default')->required()->native(false),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return $this->sectionTitle($content, 'معرفی خدمت');
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'width' => ($settings['width'] ?? null) === 'narrow' ? 'narrow' : 'default',
            'variant' => ($settings['variant'] ?? null) === 'professional' ? 'professional' : 'default',
        ];
    }
}
