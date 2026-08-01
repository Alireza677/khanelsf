<?php

namespace App\CMS\InternalLinks\Sources;

use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Contracts\ResolvesInternalLinkReference;
use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Support\InternalLinkSourceSupport;
use App\Models\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

final class PageInternalLinkSource implements InternalLinkSearchSource, ResolvesInternalLinkReference
{
    use InternalLinkSourceSupport;

    public function key(): string
    {
        return CoreActionType::Page->value;
    }

    public function label(): string
    {
        return 'برگه';
    }

    public function isAvailable(): bool
    {
        return Route::has('pages.show');
    }

    public function search(string $query, int $limit): array
    {
        if (! $this->isAvailable() || $limit < 1) {
            return [];
        }

        $contains = $this->containsPattern($query);
        $prefix = $this->prefixPattern($query);

        return Page::query()
            ->published()
            ->where(function (Builder $builder) use ($contains): void {
                $builder
                    ->where('title', 'like', $contains)
                    ->orWhere('slug', 'like', $contains);
            })
            ->orderByRaw(
                'CASE WHEN title = ? THEN 0 WHEN title LIKE ? THEN 1 ELSE 2 END',
                [$query, $prefix],
            )
            ->orderBy('id')
            ->limit($this->boundedLimit($limit))
            ->get(['id', 'title', 'slug'])
            ->map(function (Page $page) {
                $routeName = match ($page->slug) {
                    'home' => 'home',
                    'contact' => 'contact.create',
                    default => 'pages.show',
                };

                return Route::has($routeName)
                    ? $this->result($this->key(), $page, $page->title, $page->resolveNavigationUrl())
                    : null;
            })
            ->filter()
            ->values()
            ->all();
    }

    public function find(int $referenceId): ?InternalLinkSearchResult
    {
        if ($referenceId <= 0 || ! $this->isAvailable()) {
            return null;
        }

        $page = Page::query()
            ->published()
            ->whereKey($referenceId)
            ->first(['id', 'title', 'slug']);

        if (! $page instanceof Page) {
            return null;
        }

        $routeName = match ($page->slug) {
            'home' => 'home',
            'contact' => 'contact.create',
            default => 'pages.show',
        };

        return Route::has($routeName)
            ? $this->result($this->key(), $page, $page->title, $page->resolveNavigationUrl())
            : null;
    }
}
