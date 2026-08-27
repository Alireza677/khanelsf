<?php

namespace App\Data;

final readonly class NetworkLocationImportResult
{
    /** @param array<int, array{row: int|null, field: string|null, message: string}> $errors */
    public function __construct(
        public int $totalRows,
        public int $validRows,
        public int $created,
        public int $duplicates,
        public int $ignoredBlankRows,
        public array $errors = [],
    ) {}

    public function successful(): bool { return $this->errors === []; }
    public function errorMessages(int $limit = 10): array { return array_column(array_slice($this->errors, 0, $limit), 'message'); }
}
