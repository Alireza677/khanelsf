<?php

namespace App\Filament\Resources\ProjectCategoryResource\Pages;

use App\Filament\Resources\ProjectCategoryResource;
use App\Filament\Resources\Concerns\LogsFilamentEditDebug;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectCategory extends EditRecord
{
    use LogsFilamentEditDebug;

    protected static string $resource = ProjectCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
