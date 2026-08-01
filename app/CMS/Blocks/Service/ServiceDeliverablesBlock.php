<?php

namespace App\CMS\Blocks\Service;

use Filament\Forms;

final class ServiceDeliverablesBlock extends AbstractServiceBlock
{
    public function key(): string
    {
        return 'service_deliverables';
    }

    public function label(): string
    {
        return 'اقلام تحویلی';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->default('اقلام تحویلی')->maxLength(255),
            Forms\Components\Select::make('settings.style')
                ->label('سبک نمایش')->options(['list' => 'فهرست', 'cards' => 'کارت'])
                ->default('list')->required()->native(false),
            Forms\Components\Select::make('settings.columns')
                ->label('تعداد ستون‌ها')->options([1 => 'یک', 2 => 'دو', 3 => 'سه'])
                ->default(2)->required()->native(false),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return $this->sectionTitle($content, 'اقلام تحویلی');
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'style' => ($settings['style'] ?? null) === 'cards' ? 'cards' : 'list',
            'columns' => $this->integerBetween($settings['columns'] ?? null, 1, 3, 2),
        ];
    }
}
