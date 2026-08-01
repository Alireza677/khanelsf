<?php

namespace App\CMS\Blocks\Service;

use Filament\Forms;

final class ServiceBenefitsBlock extends AbstractServiceBlock
{
    public function key(): string
    {
        return 'service_benefits';
    }

    public function label(): string
    {
        return 'مزایای خدمت';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->default('مزایای خدمت')->maxLength(255),
            Forms\Components\Select::make('settings.columns')
                ->label('تعداد ستون‌ها')->options([1 => 'یک', 2 => 'دو', 3 => 'سه', 4 => 'چهار'])
                ->default(3)->required()->native(false),
            Forms\Components\Toggle::make('settings.show_icons')->label('نمایش آیکن')->default(true),
            Forms\Components\Select::make('settings.variant')
                ->label('سبک نمایش')->options(['default' => 'استاندارد', 'cards' => 'کارت'])
                ->default('default')->required()->native(false),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return $this->sectionTitle($content, 'مزایای خدمت');
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'columns' => $this->integerBetween($settings['columns'] ?? null, 1, 4, 3),
            'show_icons' => $this->boolean($settings['show_icons'] ?? null, true),
            'variant' => ($settings['variant'] ?? null) === 'cards' ? 'cards' : 'default',
        ];
    }
}
