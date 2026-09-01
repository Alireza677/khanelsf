<?php

namespace App\CMS\Collections\Service;

use App\CMS\Collections\Data\CollectionPresentation;

final readonly class ServiceArchivePresentation
{
    /** @param array<ServiceArchiveGroup> $groups */
    public function __construct(
        public array $groups,
        public CollectionPresentation $emptyCollection,
    ) {}
}
