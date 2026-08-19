<?php

namespace App\CMS\Collections\Data;

final readonly class CollectionMetaItem
{
    public function __construct(
        public ?string $label,
        public string $value,
        public ?string $icon = null,
    ) {}
}
