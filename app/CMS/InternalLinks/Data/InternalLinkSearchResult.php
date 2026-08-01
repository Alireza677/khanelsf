<?php

namespace App\CMS\InternalLinks\Data;

use InvalidArgumentException;

final readonly class InternalLinkSearchResult
{
    public function __construct(
        public string $targetKey,
        public int $referenceId,
        public string $title,
        public string $subtitle,
        public string $url,
    ) {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $targetKey) !== 1) {
            throw new InvalidArgumentException('Internal link target key must be canonical.');
        }

        if ($referenceId <= 0) {
            throw new InvalidArgumentException('Internal link reference ID must be positive.');
        }

        if (trim($title) === '') {
            throw new InvalidArgumentException('Internal link title is required.');
        }

        if (! self::isSafeUrl($url)) {
            throw new InvalidArgumentException('Internal link URL must be safe and non-empty.');
        }
    }

    /**
     * @return array{
     *     target_key: string,
     *     reference_id: int,
     *     title: string,
     *     subtitle: string,
     *     url: string
     * }
     */
    public function toArray(): array
    {
        return [
            'target_key' => $this->targetKey,
            'reference_id' => $this->referenceId,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
        ];
    }

    /**
     * Legacy Rich Text HTTP shape plus reference fields.
     *
     * @return array{
     *     title: string,
     *     type: string,
     *     url: string,
     *     subtitle: string,
     *     target_key: string,
     *     reference_id: int
     * }
     */
    public function toLegacyArray(string $typeLabel): array
    {
        return [
            'title' => $this->title,
            'type' => $typeLabel,
            'url' => $this->url,
            'subtitle' => $this->subtitle,
            'target_key' => $this->targetKey,
            'reference_id' => $this->referenceId,
        ];
    }

    private static function isSafeUrl(string $url): bool
    {
        if ($url === '' || trim($url) !== $url) {
            return false;
        }

        if (preg_match('/[\x00-\x20\x7F]/', $url) === 1
            || str_contains($url, '\\')
            || str_starts_with($url, '//')) {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $url, $matches) === 1) {
            return in_array(strtolower($matches[1]), ['http', 'https'], true)
                && filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return str_starts_with($url, '/')
            || preg_match('/^[^\s?#][^\s#]*$/u', $url) === 1;
    }
}
