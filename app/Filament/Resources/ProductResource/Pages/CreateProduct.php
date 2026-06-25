<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
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
    }

    protected function getRedirectUrl(): string
    {
        return ProductResource::getUrl('index');
    }
}
