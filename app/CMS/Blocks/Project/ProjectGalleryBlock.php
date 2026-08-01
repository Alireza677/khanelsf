<?php

namespace App\CMS\Blocks\Project;

use Filament\Forms;

final class ProjectGalleryBlock extends AbstractProjectBlock
{
    public function key(): string
    {
        return 'project_gallery';
    }

    public function label(): string
    {
        return 'گالری تصاویر';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-photo';
    }

    public function capabilities(): array
    {
        return [...parent::capabilities(), 'project_media_gallery'];
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')
                ->label('عنوان بخش')
                ->default('گالری تصاویر')
                ->maxLength(255),
            Forms\Components\Toggle::make('settings.lightbox')
                ->label('بازشدن تصاویر در نمایش تمام‌صفحه')
                ->default(true),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['title' => $this->stringOrNull($content['title'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'lightbox' => $this->boolean($settings['lightbox'] ?? null, true),
        ];
    }
}
