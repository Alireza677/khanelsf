<?php

namespace App\CMS\Blocks\Project;

use Filament\Forms;

final class RelatedProjectsBlock extends AbstractProjectBlock
{
    public function key(): string
    {
        return 'related_projects';
    }

    public function label(): string
    {
        return 'پروژه‌های مرتبط';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-rectangle-stack';
    }

    public function capabilities(): array
    {
        return [...parent::capabilities(), 'related_projects_context'];
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('پروژه‌های مرتبط')
                ->maxLength(255),
            Forms\Components\TextInput::make('settings.limit')
                ->label('حداکثر تعداد پروژه‌ها')
                ->numeric()
                ->minValue(1)
                ->maxValue(6)
                ->default(3)
                ->required(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['title' => $this->stringOrNull($content['title'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return ['limit' => $this->integerBetween($settings['limit'] ?? null, 1, 6, 3)];
    }
}
