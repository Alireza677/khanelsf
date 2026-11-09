<?php

namespace App\Filament\Resources\ProductTagResource\Pages;

use App\Filament\Resources\Concerns\HandlesProductCatalogRecords;
use App\Filament\Resources\ProductTagResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductTag extends CreateRecord
{
    use HandlesProductCatalogRecords;

    protected static string $resource = ProductTagResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getRedirectUrl(): string
    {
        return ProductTagResource::getUrl('edit', ['record' => $this->record]);
    }
}
