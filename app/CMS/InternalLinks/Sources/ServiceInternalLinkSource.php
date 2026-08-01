<?php

namespace App\CMS\InternalLinks\Sources;

use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Contracts\ResolvesInternalLinkReference;
use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Support\InternalLinkSourceSupport;
use App\Models\Service;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

final class ServiceInternalLinkSource implements InternalLinkSearchSource, ResolvesInternalLinkReference
{
    use InternalLinkSourceSupport;

    public function key(): string
    {
        return CoreActionType::Service->value;
    }

    public function label(): string
    {
        return 'خدمت';
    }

    public function isAvailable(): bool
    {
        return Route::has('services.show');
    }

    public function search(string $query, int $limit): array
    {
        if (! $this->isAvailable() || $limit < 1) {
            return [];
        }

        $contains = $this->containsPattern($query);
        $prefix = $this->prefixPattern($query);

        return Service::query()
            ->published()
            ->where(function (Builder $builder) use ($contains): void {
                $builder
                    ->where('name', 'like', $contains)
                    ->orWhere('slug', 'like', $contains);
            })
            ->orderByRaw(
                'CASE WHEN name = ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
                [$query, $prefix],
            )
            ->orderBy('id')
            ->limit($this->boundedLimit($limit))
            ->get(['id', 'name', 'slug', 'status', 'published_at'])
            ->map(fn (Service $service) => $this->result(
                $this->key(),
                $service,
                $service->name,
                $service->resolveNavigationUrl(),
            ))
            ->filter()
            ->values()
            ->all();
    }

    public function find(int $referenceId): ?InternalLinkSearchResult
    {
        if ($referenceId <= 0 || ! $this->isAvailable()) {
            return null;
        }

        $service = Service::query()
            ->published()
            ->whereKey($referenceId)
            ->first(['id', 'name', 'slug', 'status', 'published_at']);

        return $service instanceof Service
            ? $this->result(
                $this->key(),
                $service,
                $service->name,
                $service->resolveNavigationUrl(),
            )
            : null;
    }
}
