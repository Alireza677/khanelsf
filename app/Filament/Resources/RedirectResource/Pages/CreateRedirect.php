<?php

namespace App\Filament\Resources\RedirectResource\Pages;

use App\Filament\Resources\RedirectResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateRedirect extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = RedirectResource::class;
}
