<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = ProjectResource::class;

    protected function afterCreate(): void
    {
        ProjectResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }
}
