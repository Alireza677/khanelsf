<?php

namespace App\CMS\Blocks\Service;

use Filament\Forms;

final class ServiceProjectsBlock extends AbstractServiceBlock
{
    public function key(): string
    {
        return 'service_projects';
    }

    public function label(): string
    {
        return 'پروژه‌های مرتبط';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->default('پروژه‌های مرتبط')->maxLength(255),
            Forms\Components\Select::make('settings.columns')
                ->label('تعداد ستون‌ها')->options([1 => 'یک', 2 => 'دو', 3 => 'سه'])
                ->default(3)->required()->native(false),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return $this->sectionTitle($content, 'پروژه‌های مرتبط');
    }

    protected function normalizeSettings(array $settings): array
    {
        return ['columns' => $this->integerBetween($settings['columns'] ?? null, 1, 3, 3)];
    }
}
