<?php

namespace App\CMS\Blocks\Service;

use Filament\Forms;

final class ServiceGalleryBlock extends AbstractServiceBlock
{
    public function key(): string
    {
        return 'service_gallery';
    }

    public function label(): string
    {
        return 'گالری خدمت';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\TextInput::make('content.title')->label('عنوان بخش')->default('گالری خدمت')->maxLength(255),
            Forms\Components\Select::make('settings.columns')
                ->label('تعداد ستون‌ها')->options([1 => 'یک', 2 => 'دو', 3 => 'سه', 4 => 'چهار'])
                ->default(3)->required()->native(false),
            Forms\Components\Toggle::make('settings.lightbox')->label('نمایش تمام‌صفحه تصاویر')->default(true),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return $this->sectionTitle($content, 'گالری خدمت');
    }

    protected function normalizeSettings(array $settings): array
    {
        return [
            'columns' => $this->integerBetween($settings['columns'] ?? null, 1, 4, 3),
            'lightbox' => $this->boolean($settings['lightbox'] ?? null, true),
        ];
    }
}
