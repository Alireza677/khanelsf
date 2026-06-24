<?php

namespace App\Filament\Resources\SettingResource\Pages;

use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\SettingResource;
use Filament\Resources\Pages\ListRecords;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;

    public function mount(): void
    {
        $this->redirect(ManageSiteSettings::getUrl());
    }
}
