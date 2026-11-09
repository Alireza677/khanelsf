<?php

namespace App\Filament\Resources\ProductBrandResource\Pages;

use App\Filament\Resources\Concerns\HandlesProductCatalogRecords;
use App\Filament\Resources\ProductBrandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductBrand extends EditRecord
{
    use HandlesProductCatalogRecords;

    protected static string $resource = ProductBrandResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function afterSave(): void
    {
        ProductBrandResource::syncFeaturedImage($this->record, data_get($this->form->getRawState(), 'featured_media_id'));
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
