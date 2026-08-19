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
            Forms\Components\Select::make('settings.variant')
                ->label('چیدمان گالری')
                ->options(['grid' => 'شبکه‌ای', 'editorial' => 'ادیتوریال'])
                ->default('grid')->required()->native(false),
            Forms\Components\Select::make('settings.columns')
                ->label('تعداد ستون')
                ->options([2 => '۲', 3 => '۳', 4 => '۴'])
                ->default(3)->required()->native(false),
            Forms\Components\Select::make('settings.image_ratio')
                ->label('نسبت تصویر')
                ->options(['4:3' => '۴:۳', '16:10' => '۱۶:۱۰', '1:1' => '۱:۱'])
                ->default('4:3')->required()->native(false),
            Forms\Components\Select::make('settings.spacing')
                ->label('فاصله تصاویر')
                ->options(['compact' => 'فشرده', 'comfortable' => 'راحت'])
                ->default('comfortable')->required()->native(false),
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
            'variant' => ($settings['variant'] ?? null) === 'editorial' ? 'editorial' : 'grid',
            'columns' => $this->integerBetween($settings['columns'] ?? null, 2, 4, 3),
            'image_ratio' => in_array($settings['image_ratio'] ?? null, ['16:10', '1:1'], true) ? $settings['image_ratio'] : '4:3',
            'spacing' => ($settings['spacing'] ?? null) === 'compact' ? 'compact' : 'comfortable',
        ];
    }
}
