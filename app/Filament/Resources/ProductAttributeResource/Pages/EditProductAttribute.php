<?php

namespace App\Filament\Resources\ProductAttributeResource\Pages;

use App\Filament\Resources\Concerns\HandlesProductCatalogRecords;
use App\Filament\Resources\ProductAttributeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductAttribute extends EditRecord
{
    use HandlesProductCatalogRecords;

    protected static string $resource = ProductAttributeResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
