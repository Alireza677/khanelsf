<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateMenu extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = MenuResource::class;
}
