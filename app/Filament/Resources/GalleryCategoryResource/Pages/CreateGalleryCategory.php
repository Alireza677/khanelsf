<?php

namespace App\Filament\Resources\GalleryCategoryResource\Pages;

use App\Filament\Resources\GalleryCategoryResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateGalleryCategory extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = GalleryCategoryResource::class;
}
