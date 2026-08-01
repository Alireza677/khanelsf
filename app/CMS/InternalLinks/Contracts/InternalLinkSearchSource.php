<?php

namespace App\CMS\InternalLinks\Contracts;

use App\CMS\InternalLinks\Data\InternalLinkSearchResult;

interface InternalLinkSearchSource
{
    public function key(): string;

    public function label(): string;

    public function isAvailable(): bool;

    /**
     * @return array<int, InternalLinkSearchResult>
     */
    public function search(string $query, int $limit): array;
}
