<?php

namespace App\CMS\Collections\Data;

final readonly class CollectionItem
{
    /** @param array<CollectionMetaItem> $metaItems @param array<string> $badges */
    public function __construct(
        public string $title,
        public ?string $eyebrow = null,
        public ?CollectionImage $image = null,
        public ?string $icon = null,
        public ?string $excerpt = null,
        public ?CollectionAction $action = null,
        public array $metaItems = [],
        public array $badges = [],
    ) {}
}
