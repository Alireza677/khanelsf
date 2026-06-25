<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = ProductCategoryResource::class;
}
