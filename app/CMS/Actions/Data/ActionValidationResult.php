<?php

namespace App\CMS\Actions\Data;

final readonly class ActionValidationResult
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(public array $errors = []) {}

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function isInvalid(): bool
    {
        return ! $this->isValid();
    }

    /** @return list<string> */
    public function errorsFor(string $field): array
    {
        return $this->errors[$field] ?? [];
    }
}
