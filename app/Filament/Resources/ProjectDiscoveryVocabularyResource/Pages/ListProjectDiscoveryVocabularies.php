<?php

namespace App\Filament\Resources\ProjectDiscoveryVocabularyResource\Pages;

use App\Filament\Resources\ProjectDiscoveryVocabularyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProjectDiscoveryVocabularies extends ListRecords
{
    protected static string $resource = ProjectDiscoveryVocabularyResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('گروه فیلتر جدید')];
    }
}
