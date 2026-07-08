<?php

namespace App\Filament\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

trait LogsHeroV2SaveFailures
{
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (Throwable $exception) {
            $this->logHeroV2Failure('create', $exception);

            throw $exception;
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (Throwable $exception) {
            $this->logHeroV2Failure('update', $exception);

            throw $exception;
        }
    }

    protected function onValidationError(ValidationException $exception): void
    {
        if ($this->usesHeroV2Editor()) {
            Log::warning('Hero v2 editor validation failed', [
                'context' => $this->heroV2Context(),
                'record_id' => $this->heroV2RecordId(),
                'error_fields' => array_keys($exception->errors()),
                'heroes' => $this->heroV2StateSummary(),
            ]);
        }

        parent::onValidationError($exception);
    }

    private function logHeroV2Failure(string $action, Throwable $exception): void
    {
        if (! $this->usesHeroV2Editor()) {
            return;
        }

        Log::error('Hero v2 editor save failed', [
            'action' => $action,
            'context' => $this->heroV2Context(),
            'record_id' => $this->heroV2RecordId(),
            'exception' => $exception::class,
            'heroes' => $this->heroV2StateSummary(),
        ]);
    }

    private function heroV2Context(): string
    {
        return str_contains(static::class, 'TemplateResource') ? 'template' : 'page';
    }

    private function heroV2RecordId(): int|string|null
    {
        $record = $this->record ?? null;

        return $record instanceof Model ? $record->getKey() : null;
    }

    private function heroV2StateSummary(): array
    {
        $blocks = $this->data['blocks'] ?? [];

        if (! is_array($blocks)) {
            return [];
        }

        return collect($blocks)
            ->filter(fn ($block): bool => is_array($block) && ($block['type'] ?? null) === 'hero')
            ->map(fn (array $block, int|string $index): array => [
                'type' => 'hero',
                'block_index' => $index,
                'block_id' => data_get($block, 'data.block_id'),
                'schema_version' => data_get($block, 'data.schema_version'),
                'template' => data_get($block, 'data.template'),
            ])
            ->values()
            ->all();
    }
}
