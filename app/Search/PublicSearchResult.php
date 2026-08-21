<?php

namespace App\Search;

final readonly class PublicSearchResult
{
    public function __construct(
        public string $type,
        public string $typeLabel,
        public string $title,
        public ?string $excerpt,
        public string $url,
        public ?string $image,
        public ?string $meta = null,
    ) {}
}
