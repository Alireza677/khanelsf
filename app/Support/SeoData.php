<?php

namespace App\Support;

class SeoData
{
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?string $canonicalUrl = null,
        public readonly string $robots = 'index, follow',
        public readonly ?string $ogTitle = null,
        public readonly ?string $ogDescription = null,
        public readonly ?string $ogImage = null,
        public readonly string $ogType = 'website',
        public readonly string $twitterCard = 'summary_large_image',
        public readonly ?array $schema = null,
    ) {}

    public function metaTitle(): string
    {
        return $this->title;
    }

    public function metaDescription(): ?string
    {
        return $this->description;
    }

    public function openGraphTitle(): string
    {
        return $this->ogTitle ?: $this->title;
    }

    public function openGraphDescription(): ?string
    {
        return $this->ogDescription ?: $this->description;
    }
}
