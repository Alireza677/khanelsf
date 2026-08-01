<?php

namespace App\CMS\Blocks\Service;

use Filament\Forms;

final class ServiceProcessBlock extends AbstractServiceBlock
{
    public function key(): string
    {
        return 'service_process';
    }

    public function label(): string
    {
        return 'فرآیند اجرای خدمت';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->default('فرآیند اجرا')->maxLength(255),
            Forms\Components\Select::make('settings.layout')
                ->label('چیدمان')->options(['vertical' => 'عمودی', 'horizontal' => 'افقی'])
                ->default('vertical')->required()->native(false),
            Forms\Components\Toggle::make('settings.show_steps')->label('نمایش شماره مراحل')->default(true),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return $this->sectionTitle($content, 'فرآیند اجرا');
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'layout' => ($settings['layout'] ?? null) === 'horizontal' ? 'horizontal' : 'vertical',
            'show_steps' => $this->boolean($settings['show_steps'] ?? null, true),
        ];
    }
}
