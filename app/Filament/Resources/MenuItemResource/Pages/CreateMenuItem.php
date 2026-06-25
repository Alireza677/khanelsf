<?php

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Filament\Resources\MenuItemResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuItem extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = MenuItemResource::class;
}
