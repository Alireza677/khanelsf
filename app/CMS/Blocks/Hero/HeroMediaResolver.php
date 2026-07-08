<?php

namespace App\CMS\Blocks\Hero;

use Illuminate\Support\Collection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class HeroMediaResolver
{
    /** @var array<string, int|null> */
    private array $resolved = [];

    private ?Collection $media = null;

    public function resolveSourceId(?string $url): ?int
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (array_key_exists($url, $this->resolved)) {
            return $this->resolved[$url];
        }

        $path = $this->normalizedPath($url);
        $matches = ($this->media ??= Media::query()->where('collection_name', 'media_library')->get())
            ->filter(function (Media $media) use ($url, $path): bool {
                $generatedUrl = $media->getUrl();

                return hash_equals($generatedUrl, $url)
                    || ($path !== null && hash_equals($this->normalizedPath($generatedUrl) ?? '', $path));
            })
            ->values();

        return $this->resolved[$url] = $matches->count() === 1 ? (int) $matches->first()->getKey() : null;
    }

    private function normalizedPath(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        return '/'.ltrim(rawurldecode($path), '/');
    }
}
