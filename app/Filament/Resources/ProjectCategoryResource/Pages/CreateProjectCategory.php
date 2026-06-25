<?php

namespace App\Filament\Resources\ProjectCategoryResource\Pages;

use App\Filament\Resources\ProjectCategoryResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateProjectCategory extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = ProjectCategoryResource::class;
}
