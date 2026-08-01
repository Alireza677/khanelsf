<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
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
        ProductResource::syncRelatedProducts(
            $this->record,
            data_get($this->form->getRawState(), 'related_products', []),
        );
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('index');
    }
}
