<?php

namespace App\CMS\InternalLinks\Contracts;

use App\CMS\InternalLinks\Data\InternalLinkSearchResult;

interface ResolvesInternalLinkReference
{
    public function find(int $referenceId): ?InternalLinkSearchResult;
}
