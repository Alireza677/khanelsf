<?php

namespace App\CMS\Blocks\Service;

use Filament\Forms;

final class RelatedServicesBlock extends AbstractServiceBlock
{
    public function key(): string
    {
        return 'related_services';
    }

    public function label(): string
    {
        return 'خدمات مرتبط';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->default('خدمات مرتبط')->maxLength(255),
            Forms\Components\Select::make('settings.columns')
                ->label('تعداد ستون‌ها')->options([1 => 'یک', 2 => 'دو', 3 => 'سه'])
                ->default(3)->required()->native(false),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return $this->sectionTitle($content, 'خدمات مرتبط');
    }

    protected function normalizeSettings(array $settings): array
    {
        return ['columns' => $this->integerBetween($settings['columns'] ?? null, 1, 3, 3)];
    }
}
