<?php

namespace App\CMS\Collections\Service;

use App\CMS\Collections\Data\CollectionPresentation;

final readonly class ServiceArchiveGroup
{
    public function __construct(
        public int $rootId,
        public CollectionPresentation $collection,
        public bool $usesRootAsFallback,
    ) {}
}
