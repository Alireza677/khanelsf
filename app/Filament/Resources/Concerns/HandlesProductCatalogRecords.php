<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait HandlesProductCatalogRecords
{
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (ValidationException $exception) {
            $this->catalogValidationError($exception);
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (ValidationException $exception) {
            $this->catalogValidationError($exception);
        }
    }

    private function catalogValidationError(ValidationException $exception): never
    {
        throw ValidationException::withMessages(collect($exception->errors())
            ->mapWithKeys(fn ($messages, $field) => [$this->form->getStatePath().'.'.$field => $messages])->all());
    }
}
