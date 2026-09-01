<?php

namespace App\Filament\Resources\FormResource\Pages;

use App\Filament\Resources\Concerns\HasStickyFormActions;
use App\Filament\Resources\FormResource;
use Filament\Resources\Pages\EditRecord;

class EditForm extends EditRecord
{
    use HasStickyFormActions;

    protected static string $resource = FormResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return FormResource::prepareSchemaForEditor($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return FormResource::prepareSchemaForStorage($data);
    }
}
