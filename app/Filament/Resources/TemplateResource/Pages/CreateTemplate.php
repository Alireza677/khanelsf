<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\Concerns\LogsHeroV2SaveFailures;
use App\Filament\Resources\Concerns\ManagesBlockEditorIdentity;
use App\Filament\Resources\TemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplate extends CreateRecord
{
    use LogsHeroV2SaveFailures;
    use ManagesBlockEditorIdentity;

    protected static string $resource = TemplateResource::class;
}
