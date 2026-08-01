<?php

namespace App\CMS\InternalLinks\Sources;

use App\CMS\Actions\Enums\CoreActionType;
use App\CMS\InternalLinks\Contracts\InternalLinkSearchSource;
use App\CMS\InternalLinks\Contracts\ResolvesInternalLinkReference;
use App\CMS\InternalLinks\Data\InternalLinkSearchResult;
use App\CMS\InternalLinks\Support\InternalLinkSourceSupport;
use App\Models\Product;
use App\Services\ModuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Route;

final class ProductInternalLinkSource implements InternalLinkSearchSource, ResolvesInternalLinkReference
{
    use InternalLinkSourceSupport;

    public function __construct(private readonly ModuleService $modules) {}

    public function key(): string
    {
        return CoreActionType::Product->value;
    }

    public function label(): string
    {
        return 'محصول';
    }

    public function isAvailable(): bool
    {
        return $this->modules->shopEnabled() && Route::has('shop.show');
    }

    public function search(string $query, int $limit): array
    {
        if (! $this->isAvailable() || $limit < 1) {
            return [];
        }

        $contains = $this->containsPattern($query);
        $prefix = $this->prefixPattern($query);

        return Product::query()
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
            ->map(fn (Product $product) => $this->result(
                $this->key(),
                $product,
                $product->title,
                $product->resolveNavigationUrl(),
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

        $product = Product::query()
            ->published()
            ->whereKey($referenceId)
            ->first(['id', 'title', 'slug']);

        return $product instanceof Product
            ? $this->result(
                $this->key(),
                $product,
                $product->title,
                $product->resolveNavigationUrl(),
            )
            : null;
    }
}
