<?php

namespace App\Filament\Resources\ProductAttributeResource\Pages;

use App\Filament\Resources\Concerns\HandlesProductCatalogRecords;
use App\Filament\Resources\ProductAttributeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductAttribute extends CreateRecord
{
    use HandlesProductCatalogRecords;

    protected static string $resource = ProductAttributeResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getRedirectUrl(): string
    {
        return ProductAttributeResource::getUrl('edit', ['record' => $this->record]);
    }
}
