<?php

namespace App\CMS\InternalLinks\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

/** Optional admin browsing capability; the existing search contract is unchanged. */
interface BrowsesInternalLinkTargets
{
    public function browse(string $query, int $page = 1, int $perPage = 20, array $filters = []): LengthAwarePaginator;
}
