<?php

namespace App\CMS\Collections\Data;

final readonly class CollectionEmptyState
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $icon = null,
    ) {}
}
