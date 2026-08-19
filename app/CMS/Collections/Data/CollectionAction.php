<?php

namespace App\CMS\Collections\Data;

final readonly class CollectionAction
{
    public function __construct(
        public string $label,
        public string $href,
        public ?string $target = null,
        public ?string $rel = null,
    ) {}
}
