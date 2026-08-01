<?php

namespace App\CMS\Blocks\Project;

use Filament\Forms;

final class ProjectServicesBlock extends AbstractProjectBlock
{
    public function key(): string
    {
        return 'project_services';
    }

    public function label(): string
    {
        return 'خدمات مرتبط';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-wrench-screwdriver';
    }

    public function capabilities(): array
    {
        return [...parent::capabilities(), 'project_services_relation'];
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('خدمات مرتبط')
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
