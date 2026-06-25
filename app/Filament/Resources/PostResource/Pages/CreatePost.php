<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = PostResource::class;

    protected function afterCreate(): void
    {
        PostResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }
}
