<?php

namespace App\Contracts;

interface VideoProviderAdapter
{
    /** @return array{provider: string, external_id: string, source_url: string}|null */
    public function parseUrl(string $url): ?array;

    public function embedUrl(string $externalId): ?string;

    /** Best effort, cached on save; never called from public rendering.
     * @return array{thumbnail_url?: string, duration_seconds?: int}
     */
    public function fetchMetadata(string $externalId): array;

    public function trustedThumbnailUrl(?string $url): ?string;
}
