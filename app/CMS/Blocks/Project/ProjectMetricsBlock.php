<?php

namespace App\CMS\Blocks\Project;

use Filament\Forms;

final class ProjectMetricsBlock extends AbstractProjectBlock
{
    public function key(): string
    {
        return 'project_metrics';
    }

    public function label(): string
    {
        return 'شاخص‌ها و دستاوردها';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-chart-bar';
    }

    public function capabilities(): array
    {
        return [...parent::capabilities(), 'project_metrics_relation'];
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('نتایج پروژه')
                ->maxLength(255),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['title' => $this->stringOrNull($content['title'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [];
    }
}
