<?php

namespace App\Filament\Resources\RedirectResource\Pages;

use App\Filament\Resources\RedirectResource;
use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRedirect extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = RedirectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
