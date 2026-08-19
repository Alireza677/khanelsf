<?php

namespace App\CMS\Collections\Data;

final readonly class CollectionImage
{
    public function __construct(
        public string $url,
        public string $alt,
    ) {}
}
