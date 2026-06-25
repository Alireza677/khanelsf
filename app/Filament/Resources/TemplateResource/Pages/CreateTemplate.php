<?php

namespace App\Filament\Resources\TemplateResource\Pages;

use App\Filament\Resources\TemplateResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateTemplate extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = TemplateResource::class;
}
