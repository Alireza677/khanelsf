<?php

namespace App\CMS\InternalLinks\Support;

use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

trait InternalLinkSourceSupport
{
    protected function paginateTargets(
        Builder $builder,
        string $titleColumn,
        string $query,
        int $page,
        int $perPage,
        array $extraSearchColumns = [],
    ): LengthAwarePaginator {
        $perPage = $this->boundedLimit($perPage);
        $page = max(1, $page);

        if (! $this->isAvailable()) {
            return new LengthAwarePaginator([], 0, $perPage, $page);
        }

        if ($query !== '') {
            $builder->where(function (Builder $builder) use ($titleColumn, $query, $extraSearchColumns): void {
                foreach ([$titleColumn, 'slug', ...$extraSearchColumns] as $column) {
                    $builder->orWhereRaw($column." LIKE ? ESCAPE '!'", ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query).'%']);
                }

                if (ctype_digit($query)) {
                    $builder->orWhere('id', (int) $query);
                }
            });
        }

        return $builder->orderBy($titleColumn)->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    protected function browserRow(Model $record, string $title, ?string $url, array $metadata = []): array
    {
        try {
            $result = $this->result($this->key(), $record, $title, $url);
        } catch (InvalidArgumentException) {
            $result = null;
        }

        return [
            'reference_id' => (int) $record->getKey(),
            'title' => $title,
            'subtitle' => $result?->subtitle ?? (string) $record->getAttribute('slug'),
            'url' => $result?->url,
            'status' => $result ? 'منتشرشده' : 'در دسترس نیست',
            'selectable' => $result !== null,
            ...$metadata,
        ];
    }

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
