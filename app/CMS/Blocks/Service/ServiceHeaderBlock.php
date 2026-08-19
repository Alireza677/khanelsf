<?php

namespace App\CMS\Blocks\Service;

use App\CMS\Actions\Filament\ActionPicker;
use Filament\Forms;

final class ServiceHeaderBlock extends AbstractServiceBlock
{
    protected function defaultHeadingLevel(): string
    {
        return 'h1';
    }

    public function key(): string
    {
        return 'service_header';
    }

    public function label(): string
    {
        return 'سربرگ خدمت';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-identification';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\Toggle::make('settings.show_excerpt')->label('نمایش خلاصه')->default(true),
            Forms\Components\Toggle::make('settings.show_image')->label('نمایش تصویر شاخص')->default(true),
            Forms\Components\Select::make('settings.alignment')
                ->label('چیدمان متن')
                ->options(['start' => 'ابتدای سطر', 'center' => 'وسط'])
                ->default('start')->required()->native(false),
            Forms\Components\Section::make('دعوت به اقدام')
                ->schema([
                    Forms\Components\TextInput::make('settings.primary_action.label')->label('متن اقدام اصلی')->maxLength(120),
                    ActionPicker::make('settings.primary_action.action')->label('مقصد اقدام اصلی'),
                    Forms\Components\TextInput::make('settings.secondary_action.label')->label('متن اقدام ثانویه')->maxLength(120),
                    ActionPicker::make('settings.secondary_action.action')->label('مقصد اقدام ثانویه'),
                ])->columns(2)->columnSpanFull(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return [];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'show_excerpt' => $this->boolean($settings['show_excerpt'] ?? null, true),
            'show_image' => $this->boolean($settings['show_image'] ?? null, true),
            'alignment' => ($settings['alignment'] ?? null) === 'center' ? 'center' : 'start',
            'variant' => 'modern-split',
            'image_position' => 'end',
            'primary_action' => $this->action($settings['primary_action'] ?? null),
            'secondary_action' => $this->action($settings['secondary_action'] ?? null),
        ];
    }

    private function action(mixed $value): array
    {
        $value = is_array($value) ? $value : [];

        return [
            'label' => $this->stringOrNull($value['label'] ?? null),
            'action' => is_array($value['action'] ?? null) ? $value['action'] : null,
        ];
    }
}
