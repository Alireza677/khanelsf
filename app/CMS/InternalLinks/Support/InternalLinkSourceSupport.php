<?php

namespace App\CMS\InternalLinks\Support;

use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use Illuminate\Database\Eloquent\Model;

trait InternalLinkSourceSupport
{
    protected function boundedLimit(int $limit): int
    {
        return max(1, min($limit, 50));
    }

    protected function containsPattern(string $query): string
    {
        return '%'.$this->escapeLike($query).'%';
    }

    protected function prefixPattern(string $query): string
    {
        return $this->escapeLike($query).'%';
    }

    protected function result(
        string $targetKey,
        Model $model,
        mixed $title,
        ?string $url,
    ): ?InternalLinkSearchResult {
        $title = is_scalar($title) ? trim((string) $title) : '';
        $url = is_string($url) ? trim($url) : '';
        $referenceId = (int) $model->getKey();

        if ($referenceId <= 0 || $title === '' || $url === '') {
            return null;
        }

        if (str_starts_with($url, '/')) {
            $url = url($url);
        }

        return new InternalLinkSearchResult(
            targetKey: $targetKey,
            referenceId: $referenceId,
            title: $title,
            subtitle: parse_url($url, PHP_URL_PATH) ?: $url,
            url: $url,
        );
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
