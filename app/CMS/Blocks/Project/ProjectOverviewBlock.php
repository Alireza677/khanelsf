<?php

namespace App\CMS\Blocks\Project;

use Filament\Forms;

final class ProjectOverviewBlock extends AbstractProjectBlock
{
    public function key(): string
    {
        return 'project_overview';
    }

    public function label(): string
    {
        return 'نمای کلی';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-identification';
    }

    public function capabilities(): array
    {
        return [...parent::capabilities(), 'case_study_overview'];
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('نمای کلی پروژه')
                ->maxLength(255),
            Forms\Components\Select::make('settings.date_format')
                ->label('قالب نمایش تاریخ')
                ->options(['human' => 'تاریخ کامل', 'year' => 'فقط سال'])
                ->default('human')
                ->required()
                ->native(false),
            ...collect([
                'show_client' => 'کارفرما',
                'show_location' => 'موقعیت پروژه',
                'show_industry' => 'حوزه فعالیت',
                'show_project_type' => 'نوع پروژه',
                'show_dates' => 'تاریخ‌ها',
            ])->map(fn (string $label, string $name) => Forms\Components\Toggle::make("settings.{$name}")
                ->label("نمایش {$label}")
                ->default(true))->all(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['title' => $this->stringOrNull($content['title'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'show_client' => $this->boolean($settings['show_client'] ?? null, true),
            'show_location' => $this->boolean($settings['show_location'] ?? null, true),
            'show_industry' => $this->boolean($settings['show_industry'] ?? null, true),
            'show_project_type' => $this->boolean($settings['show_project_type'] ?? null, true),
            'show_dates' => $this->boolean($settings['show_dates'] ?? null, true),
            'date_format' => ($settings['date_format'] ?? null) === 'year' ? 'year' : 'human',
        ];
    }
}
