<?php

namespace App\CMS\Collections\Data;

final readonly class CollectionPresentation
{
    /** @param array<CollectionItem> $items */
    public function __construct(
        public string $title,
        public array $items,
        public ?string $eyebrow = null,
        public ?string $description = null,
        public ?CollectionPagination $pagination = null,
        public ?CollectionEmptyState $emptyState = null,
        public string $variant = 'clean_grid',
        public int $columns = 3,
        public string $direction = 'rtl',
        public ?array $filters = null,
        public ?array $sort = null,
    ) {}
}
