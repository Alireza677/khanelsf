<?php

namespace App\CMS\Blocks\Project;

use Filament\Forms;

final class ProjectStoryBlock extends AbstractProjectBlock
{
    private const DEFAULT_HEADINGS = [
        'challenge' => 'چالش پروژه',
        'solution' => 'راهکار اجراشده',
        'results_summary' => 'خلاصه نتایج',
        'client_quote' => 'نظر کارفرما',
    ];

    public function key(): string
    {
        return 'project_story';
    }

    public function label(): string
    {
        return 'روایت مطالعه موردی';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-book-open';
    }

    public function capabilities(): array
    {
        return [...parent::capabilities(), 'case_study_narrative'];
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان اصلی بخش')
                ->default('روایت پروژه')
                ->maxLength(255)
                ->columnSpanFull(),
            ...collect(self::DEFAULT_HEADINGS)
                ->map(fn (string $label, string $key) => Forms\Components\Section::make($label)
                    ->schema([
                        Forms\Components\TextInput::make("content.headings.{$key}")
                            ->label('عنوان نمایشی')
                            ->default($label)
                            ->maxLength(255),
                        Forms\Components\Toggle::make("settings.show_{$key}")
                            ->label('نمایش این بخش')
                            ->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull())
                ->values()
                ->all(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        $headings = is_array($content['headings'] ?? null) ? $content['headings'] : [];

        return [
            'title' => $this->stringOrNull($content['title'] ?? null),
            'headings' => collect(self::DEFAULT_HEADINGS)
                ->mapWithKeys(fn (string $default, string $key): array => [
                    $key => $this->stringOrNull($headings[$key] ?? null) ?? $default,
                ])
                ->all(),
        ];
    }

    protected function normalizeSettings(array $settings): array
    {
        return collect(array_keys(self::DEFAULT_HEADINGS))
            ->mapWithKeys(fn (string $key): array => [
                "show_{$key}" => $this->boolean($settings["show_{$key}"] ?? null, true),
            ])
            ->all();
    }
}
