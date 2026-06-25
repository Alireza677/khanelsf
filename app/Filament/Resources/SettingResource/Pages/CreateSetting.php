<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Resources\SettingResource;
use App\Filament\Resources\Concerns\LogsFilamentCreateDebug;
use Filament\Resources\Pages\CreateRecord;

class CreateSetting extends CreateRecord
{
    use LogsFilamentCreateDebug;

    protected static string $resource = SettingResource::class;
}
