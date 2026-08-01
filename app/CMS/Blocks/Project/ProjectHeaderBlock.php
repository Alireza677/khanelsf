<?php

namespace App\CMS\Blocks\Project;

use Filament\Forms;
use Filament\Forms\Get;

final class ProjectHeaderBlock extends AbstractProjectBlock
{
    protected function defaultHeadingLevel(): string
    {
        return 'h1';
    }

    public function key(): string
    {
        return 'project_header';
    }

    public function label(): string
    {
        return 'سربرگ مطالعه موردی';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-identification';
    }

    public function capabilities(): array
    {
        return [...parent::capabilities(), 'case_study_header'];
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.eyebrow')
                ->label('برچسب بالای عنوان')
                ->placeholder('مطالعه موردی')
                ->maxLength(120)
                ->columnSpanFull(),
            Forms\Components\Select::make('settings.variant')
                ->label('طرح نمایش')
                ->options([
                    'default' => 'استاندارد',
                    'split' => 'دو ستونه',
                ])
                ->default('default')
                ->required()
                ->native(false),
            Forms\Components\Select::make('settings.alignment')
                ->label('چیدمان متن')
                ->options([
                    'start' => 'ابتدای سطر',
                    'center' => 'وسط',
                ])
                ->default('start')
                ->required()
                ->native(false),
            Forms\Components\Section::make('اجزای قابل نمایش')
                ->schema([
                    ...collect([
                        'show_image' => 'تصویر شاخص',
                        'show_category' => 'دسته‌بندی',
                        'show_client' => 'کارفرما',
                        'show_location' => 'موقعیت پروژه',
                        'show_industry' => 'حوزه فعالیت',
                        'show_project_type' => 'نوع پروژه',
                        'show_dates' => 'تاریخ پروژه',
                    ])->map(fn (string $label, string $name) => Forms\Components\Toggle::make("settings.{$name}")
                        ->label("نمایش {$label}")
                        ->default(true))->all(),
                    Forms\Components\Select::make('settings.date_format')
                        ->label('قالب تاریخ')
                        ->options([
                            'human' => 'تاریخ کامل',
                            'year' => 'فقط سال',
                        ])
                        ->default('human')
                        ->required()
                        ->native(false)
                        ->visible(fn (Get $get): bool => (bool) $get('settings.show_dates')),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\Section::make('دعوت به اقدام اصلی')
                ->schema([
                    Forms\Components\Toggle::make('settings.show_cta')
                        ->label('نمایش دکمه اصلی')
                        ->default(false)
                        ->live(),
                    Forms\Components\Select::make('settings.cta_type')
                        ->label('نوع دکمه اصلی')
                        ->options([
                            'project' => 'لینک خارجی ثبت‌شده برای پروژه',
                            'marketing' => 'لینک بازاریابی',
                        ])
                        ->default('project')
                        ->required()
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => (bool) $get('settings.show_cta')),
                    Forms\Components\TextInput::make('settings.cta_label')
                        ->label('متن دکمه اصلی')
                        ->default('مشاهده پروژه')
                        ->maxLength(120)
                        ->visible(fn (Get $get): bool => (bool) $get('settings.show_cta')),
                    Forms\Components\TextInput::make('settings.cta_target')
                        ->label('نشانی لینک بازاریابی')
                        ->helperText('در حالت لینک پروژه، مقصد از فیلد لینک خارجی خود پروژه خوانده می‌شود.')
                        ->maxLength(2048)
                        ->visible(fn (Get $get): bool => (bool) $get('settings.show_cta')
                            && $get('settings.cta_type') === 'marketing'),
                ])
                ->columns(2)
                ->columnSpanFull(),
            Forms\Components\Section::make('دعوت به اقدام ثانویه')
                ->schema([
                    Forms\Components\Toggle::make('settings.show_secondary_cta')
                        ->label('نمایش دکمه ثانویه')
                        ->default(false)
                        ->live(),
                    Forms\Components\TextInput::make('settings.secondary_cta_label')
                        ->label('متن دکمه ثانویه')
                        ->maxLength(120)
                        ->visible(fn (Get $get): bool => (bool) $get('settings.show_secondary_cta')),
                    Forms\Components\TextInput::make('settings.secondary_cta_target')
                        ->label('نشانی دکمه ثانویه')
                        ->maxLength(2048)
                        ->visible(fn (Get $get): bool => (bool) $get('settings.show_secondary_cta')),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return [
            'eyebrow' => $this->stringOrNull($content['eyebrow'] ?? null),
        ];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'variant' => ($settings['variant'] ?? null) === 'split' ? 'split' : 'default',
            'alignment' => ($settings['alignment'] ?? null) === 'center' ? 'center' : 'start',
            'show_image' => $this->boolean($settings['show_image'] ?? null, true),
            'show_category' => $this->boolean($settings['show_category'] ?? null, true),
            'show_client' => $this->boolean($settings['show_client'] ?? null, true),
            'show_location' => $this->boolean($settings['show_location'] ?? null, true),
            'show_industry' => $this->boolean($settings['show_industry'] ?? null, true),
            'show_project_type' => $this->boolean($settings['show_project_type'] ?? null, true),
            'show_dates' => $this->boolean($settings['show_dates'] ?? null, true),
            'date_format' => ($settings['date_format'] ?? null) === 'year' ? 'year' : 'human',
            'show_cta' => $this->boolean($settings['show_cta'] ?? null, false),
            'cta_type' => ($settings['cta_type'] ?? null) === 'marketing' ? 'marketing' : 'project',
            'cta_label' => $this->stringOrNull($settings['cta_label'] ?? null) ?? 'مشاهده پروژه',
            'cta_target' => $this->stringOrNull($settings['cta_target'] ?? null),
            'show_secondary_cta' => $this->boolean($settings['show_secondary_cta'] ?? null, false),
            'secondary_cta_label' => $this->stringOrNull($settings['secondary_cta_label'] ?? null),
            'secondary_cta_target' => $this->stringOrNull($settings['secondary_cta_target'] ?? null),
        ];
    }
}
