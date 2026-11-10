<?php

namespace App\CMS\Blocks\Product;

use Filament\Forms;

final class ProductDetailsBlock extends AbstractProductBlock
{
    public function key(): string
    {
        return 'product_details';
    }

    public function label(): string
    {
        return 'تب‌های اطلاعات محصول';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-rectangle-group';
    }

    protected function schema(): array
    {
        return [
            Forms\Components\RichEditor::make('content.buying_guide')->label('راهنمای خرید اختیاری')
                ->helperText('فقط محتوای واقعی و تأییدشده فروشگاه را وارد کنید.')->columnSpanFull(),
            Forms\Components\Select::make('settings.default_tab')->label('تب اولیه در صورت داشتن محتوا')
                ->options(['description' => 'توضیحات', 'specifications' => 'مشخصات فنی', 'attributes' => 'ویژگی‌های عمومی', 'documents' => 'اسناد و فایل‌ها', 'guide' => 'راهنمای خرید'])
                ->default('description')->required(),
        ];
    }

    protected function normalizeContent(array $content): array
    {
        return ['buying_guide' => $this->stringOrNull($content['buying_guide'] ?? null)];
    }

    protected function normalizeSettings(array $settings): array
    {
        return ['default_tab' => in_array($settings['default_tab'] ?? null, ['description', 'specifications', 'attributes', 'documents', 'guide'], true) ? $settings['default_tab'] : 'description'];
    }
}
