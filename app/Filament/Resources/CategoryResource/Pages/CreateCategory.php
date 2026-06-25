<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = CategoryResource::class;
}
