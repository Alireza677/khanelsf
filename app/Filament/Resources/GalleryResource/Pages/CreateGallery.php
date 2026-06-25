<?php

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Filament\Resources\GalleryResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateGallery extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = GalleryResource::class;

    protected function afterCreate(): void
    {
        GalleryResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }
}
