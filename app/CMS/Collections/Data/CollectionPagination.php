<?php

namespace App\CMS\Collections\Data;

final readonly class CollectionPagination
{
    /** @param array<CollectionPaginationLink> $links */
    public function __construct(
        public int $currentPage,
        public int $lastPage,
        public ?string $previousUrl,
        public ?string $nextUrl,
        public array $links,
        public string $ariaLabel = 'صفحه‌بندی',
    ) {}
}
