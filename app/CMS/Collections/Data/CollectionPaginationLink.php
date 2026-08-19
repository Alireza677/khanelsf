<?php

namespace App\CMS\Collections\Data;

final readonly class CollectionPaginationLink
{
    public function __construct(
        public string $label,
        public ?string $url,
        public bool $active = false,
    ) {}
}
