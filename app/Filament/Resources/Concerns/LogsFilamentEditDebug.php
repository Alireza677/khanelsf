<?php

namespace App\Filament\Resources\Concerns;

use App\Support\TemporaryDebugLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

trait LogsFilamentEditDebug
{
    // TEMP DEBUG - remove after production save issue is fixed.
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $modelClass = $record::class;

        TemporaryDebugLogger::filamentSaveStarted('update', static::class, $modelClass, $record, $data);

        try {
            $updatedRecord = parent::handleRecordUpdate($record, $data);

            TemporaryDebugLogger::filamentSaveCompleted('update', static::class, $modelClass, $updatedRecord, $data);

            return $updatedRecord;
        } catch (Throwable $exception) {
            TemporaryDebugLogger::filamentSaveFailed('update', static::class, $modelClass, $record, $data, $exception);

            throw $exception;
        }
    }

    // TEMP DEBUG - remove after production save issue is fixed.
    protected function onValidationError(ValidationException $exception): void
    {
        $record = method_exists($this, 'getRecord') ? $this->getRecord() : null;

        TemporaryDebugLogger::logException('TEMP DEBUG - Filament update validation failed', $exception, null, [
            'action' => 'update-validation',
            'component' => static::class,
            'model_class' => $record instanceof Model ? $record::class : null,
            'model_id' => $record instanceof Model ? $record->getKey() : null,
            'validation_errors' => TemporaryDebugLogger::validationErrorSummary($exception),
        ]);

        parent::onValidationError($exception);
    }
}
