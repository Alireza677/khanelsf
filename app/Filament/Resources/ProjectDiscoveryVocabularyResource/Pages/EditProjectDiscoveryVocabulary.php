<?php

namespace App\Filament\Resources\ProjectDiscoveryVocabularyResource\Pages;

use App\Filament\Resources\ProjectDiscoveryVocabularyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectDiscoveryVocabulary extends EditRecord
{
    protected static string $resource = ProjectDiscoveryVocabularyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
