<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductCategory extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
