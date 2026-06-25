<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'fi-page-editor-locked-scroll',
        ];
    }

    protected function afterCreate(): void
    {
        PostResource::syncFeaturedImage(
            $this->record,
            data_get($this->form->getRawState(), 'featured_media_id'),
        );
    }
}
