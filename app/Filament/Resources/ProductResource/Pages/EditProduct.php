<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterSave(): void
    {
        ProductResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
        ProductResource::syncMediaLibraryCollection(
            $this->record,
            'gallery',
            data_get($this->form->getRawState(), 'gallery_media_ids', []),
        );
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('index');
    }

    public function getTitle(): string
    {
        return 'ویرایش محصول';
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()->label('ذخیره تغییرات');
    }

    protected function getCancelFormAction(): Actions\Action
    {
        return parent::getCancelFormAction()->label('انصراف');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->label('پیش‌نمایش')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('admin.preview.products.show', $this->record))
                ->openUrlInNewTab(),
            Actions\Action::make('viewPublic')
                ->label('مشاهده محصول در سایت')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (): string => ProductResource::publicUrl($this->record))
                ->openUrlInNewTab()
                ->visible(fn (): bool => ProductResource::isPubliclyVisible($this->record)),
            Actions\DeleteAction::make()->label('حذف محصول'),
        ];
    }
}
