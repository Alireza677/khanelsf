<?php

namespace App\Filament\Resources\GalleryCategoryResource\Pages;

use App\Filament\Resources\GalleryCategoryResource;
use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGalleryCategory extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = GalleryCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
