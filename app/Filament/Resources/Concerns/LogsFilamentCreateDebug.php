<?php

namespace App\Filament\Resources\Concerns;

use App\Support\TemporaryDebugLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

trait LogsFilamentCreateDebug
{
    // TEMP DEBUG - remove after production save issue is fixed.
    protected function handleRecordCreation(array $data): Model
    {
        $modelClass = $this->temporaryDebugModelClass();

        TemporaryDebugLogger::filamentSaveStarted('create', static::class, $modelClass, null, $data);

        try {
            $record = parent::handleRecordCreation($data);

            TemporaryDebugLogger::filamentSaveCompleted('create', static::class, $modelClass, $record, $data);

            return $record;
        } catch (Throwable $exception) {
            TemporaryDebugLogger::filamentSaveFailed('create', static::class, $modelClass, null, $data, $exception);

            throw $exception;
        }
    }

    // TEMP DEBUG - remove after production save issue is fixed.
    protected function onValidationError(ValidationException $exception): void
    {
        TemporaryDebugLogger::logException('TEMP DEBUG - Filament create validation failed', $exception, null, [
            'action' => 'create-validation',
            'component' => static::class,
            'model_class' => $this->temporaryDebugModelClass(),
            'validation_errors' => TemporaryDebugLogger::validationErrorSummary($exception),
        ]);

        parent::onValidationError($exception);
    }

    private function temporaryDebugModelClass(): ?string
    {
        $resource = static::getResource();

        return method_exists($resource, 'getModel') ? $resource::getModel() : null;
    }
}
