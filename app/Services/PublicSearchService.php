<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Search\PublicSearchResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

final class PublicSearchService
{
    public const PER_SOURCE_LIMIT = 8;

    public const TYPES = ['product', 'project', 'service', 'post'];

    public function __construct(private readonly ModuleService $modules) {}

    /** @return array<string, string> */
    public function availableSources(): array
    {
        return array_filter([
            'product' => $this->modules->shopEnabled() && Route::has('shop.show') ? 'محصولات' : null,
            'project' => $this->modules->projectsEnabled() && Route::has('projects.show') ? 'پروژه‌ها' : null,
            'service' => $this->modules->publicServicesEnabled() && Route::has('services.show') ? 'خدمات' : null,
            'post' => Route::has('blog.show') ? 'مرکز یادگیری' : null,
        ]);
    }

    /**
     * @param  array<int, string>|null  $requestedTypes
     * @return array<string, Collection<int, PublicSearchResult>>
     */
    public function search(string $query, ?array $requestedTypes = null): array
    {
        $available = $this->availableSources();
        $types = $requestedTypes === null
            ? array_keys($available)
            : array_values(array_intersect(self::TYPES, array_keys($available), $requestedTypes));

        $results = [];

        foreach ($types as $type) {
            $results[$type] = match ($type) {
                'product' => $this->products($query),
                'project' => $this->projects($query),
                'service' => $this->services($query),
                'post' => $this->posts($query),
            };
        }

        return $results;
    }

    private function products(string $term): Collection
    {
        return Product::query()
            ->with('media')
            ->published()
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%"))
            ->latest('published_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (Product $product) => $this->result(
                'product',
                'محصول',
                $product->title,
                $product->excerpt,
                $product->resolveNavigationUrl(),
                $product->featuredImageUrl('thumb'),
                filled($product->sku) ? 'شناسه: '.$product->sku : null,
            ));
    }

    private function projects(string $term): Collection
    {
        return Project::query()
            ->with('media')
            ->published()
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%")
                ->orWhere('location', 'like', "%{$term}%")
                ->orWhere('project_type', 'like', "%{$term}%"))
            ->latest('published_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (Project $project) => $this->result(
                'project',
                'پروژه',
                $project->title,
                $project->excerpt,
                $project->resolveNavigationUrl(),
                $project->featuredImageUrl('thumb'),
                $project->location,
            ));
    }

    private function services(string $term): Collection
    {
        return Service::query()
            ->with('media')
            ->published()
            ->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%"))
            ->orderBy('sort_order')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (Service $service) => $this->result(
                'service',
                'خدمت',
                $service->name,
                $service->excerpt,
                $service->resolveNavigationUrl(),
                $service->featuredImageUrl('thumb'),
            ));
    }

    private function posts(string $term): Collection
    {
        return Post::query()
            ->with(['category', 'media'])
            ->published()
            ->where(fn (Builder $query) => $query
                ->where('title', 'like', "%{$term}%")
                ->orWhere('excerpt', 'like', "%{$term}%"))
            ->latest('published_at')
            ->limit(self::PER_SOURCE_LIMIT)
            ->get()
            ->map(fn (Post $post) => $this->result(
                'post',
                'مرکز یادگیری',
                $post->title,
                $post->excerpt,
                $post->resolveNavigationUrl(),
                $post->featuredImageUrl('thumb'),
                $post->category?->title,
            ));
    }

    private function result(
        string $type,
        string $typeLabel,
        string $title,
        ?string $excerpt,
        ?string $url,
        ?string $image,
        ?string $meta = null,
    ): PublicSearchResult {
        return new PublicSearchResult(
            $type,
            $typeLabel,
            $title,
            filled($excerpt) ? Str::limit(strip_tags($excerpt), 180) : null,
            $url ?? '#',
            $image,
            filled($meta) ? $meta : null,
        );
    }
}
