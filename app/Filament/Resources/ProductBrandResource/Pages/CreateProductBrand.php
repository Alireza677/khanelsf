<?php

namespace App\Filament\Resources\ProductBrandResource\Pages;

use App\Filament\Resources\Concerns\HandlesProductCatalogRecords;
use App\Filament\Resources\ProductBrandResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductBrand extends CreateRecord
{
    use HandlesProductCatalogRecords;

    protected static string $resource = ProductBrandResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function afterCreate(): void
    {
        ProductBrandResource::syncFeaturedImage($this->record, data_get($this->form->getRawState(), 'featured_media_id'));
    }

    protected function getRedirectUrl(): string
    {
        return ProductBrandResource::getUrl('edit', ['record' => $this->record]);
    }
}
