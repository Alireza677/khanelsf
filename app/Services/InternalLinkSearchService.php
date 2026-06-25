<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InternalLinkSearchService
{
    public function __construct(private readonly ModuleService $modules) {}

    public function search(string $query, int $limit = 20): array
    {
        $query = trim(Str::squish($query));

        if (mb_strlen($query) < 2) {
            return [];
        }

        $limit = max(1, min($limit, 50));
        $results = collect();

        foreach ($this->searchables() as $searchable) {
            if ($results->count() >= $limit) {
                break;
            }

            $results = $results->merge(
                $this->searchModel($searchable, $query, $limit - $results->count())
            );
        }

        return $results
            ->take($limit)
            ->values()
            ->all();
    }

    private function searchables(): array
    {
        return [
            [
                'model' => Post::class,
                'type' => 'نوشته',
                'route' => 'blog.show',
                'title_column' => 'title',
                'scope' => 'published',
                'columns' => ['title', 'slug', 'excerpt', 'seo_description'],
            ],
            [
                'model' => Page::class,
                'type' => 'برگه',
                'route' => 'pages.show',
                'title_column' => 'title',
                'scope' => 'published',
                'columns' => ['title', 'slug', 'seo_description'],
            ],
            [
                'model' => Product::class,
                'type' => 'محصول',
                'route' => 'shop.show',
                'title_column' => 'title',
                'scope' => 'published',
                'module' => 'shopEnabled',
                'columns' => ['title', 'slug', 'excerpt', 'sku', 'seo_description'],
            ],
            [
                'model' => Project::class,
                'type' => 'پروژه',
                'route' => 'projects.show',
                'title_column' => 'title',
                'scope' => 'published',
                'module' => 'projectsEnabled',
                'columns' => ['title', 'slug', 'excerpt', 'client_name', 'seo_description'],
            ],
            [
                'model' => Gallery::class,
                'type' => 'گالری',
                'route' => 'galleries.show',
                'title_column' => 'title',
                'scope' => 'published',
                'module' => 'galleriesEnabled',
                'columns' => ['title', 'slug', 'excerpt', 'seo_description'],
            ],
            [
                'model' => Category::class,
                'type' => 'دسته‌بندی نوشته',
                'route' => 'blog.category',
                'title_column' => 'title',
                'scope' => 'published',
                'columns' => ['title', 'slug', 'description'],
            ],
            [
                'model' => ProductCategory::class,
                'type' => 'دسته‌بندی محصول',
                'route' => 'shop.category',
                'title_column' => 'name',
                'scope' => 'active',
                'module' => 'shopEnabled',
                'columns' => ['name', 'slug', 'description', 'seo_description'],
            ],
            [
                'model' => ProjectCategory::class,
                'type' => 'دسته‌بندی پروژه',
                'route' => 'projects.category',
                'title_column' => 'name',
                'scope' => 'active',
                'module' => 'projectsEnabled',
                'columns' => ['name', 'slug', 'description', 'seo_description'],
            ],
            [
                'model' => GalleryCategory::class,
                'type' => 'دسته‌بندی گالری',
                'route' => 'galleries.category',
                'title_column' => 'name',
                'scope' => 'active',
                'module' => 'galleriesEnabled',
                'columns' => ['name', 'slug', 'description', 'seo_description'],
            ],
        ];
    }

    private function searchModel(array $searchable, string $term, int $limit): Collection
    {
        $modelClass = $searchable['model'];
        $routeName = $searchable['route'];

        if (
            $limit < 1
            || ! class_exists($modelClass)
            || ! Route::has($routeName)
            || ! $this->moduleIsEnabled($searchable['module'] ?? null)
        ) {
            return collect();
        }

        /** @var Model $model */
        $model = new $modelClass;
        $table = $model->getTable();

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'slug')) {
            return collect();
        }

        $titleColumn = $searchable['title_column'];

        if (! Schema::hasColumn($table, $titleColumn)) {
            return collect();
        }

        $columns = collect($searchable['columns'])
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        if ($columns === []) {
            return collect();
        }

        /** @var Builder $builder */
        $builder = $modelClass::query();
        $this->applyScope($builder, $model, $searchable['scope'] ?? null);

        $likeTerm = '%'.$this->escapeLike($term).'%';

        $builder->where(function (Builder $builder) use ($columns, $likeTerm): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}($column, 'like', $likeTerm);
            }
        });

        $this->applyOrdering($builder, $table);

        return $builder
            ->limit($limit)
            ->get()
            ->map(fn (Model $record): array => $this->formatResult($record, $searchable));
    }

    private function applyScope(Builder $builder, Model $model, ?string $scope): void
    {
        if (! $scope) {
            return;
        }

        $method = 'scope'.Str::studly($scope);

        if (method_exists($model, $method)) {
            $builder->{$scope}();
        }
    }

    private function applyOrdering(Builder $builder, string $table): void
    {
        if (Schema::hasColumn($table, 'published_at')) {
            $builder->latest('published_at');

            return;
        }

        if (Schema::hasColumn($table, 'sort_order')) {
            $builder->orderBy('sort_order')->latest('updated_at');

            return;
        }

        if (Schema::hasColumn($table, 'updated_at')) {
            $builder->latest('updated_at');
        }
    }

    private function formatResult(Model $record, array $searchable): array
    {
        $url = route($searchable['route'], $record->getAttribute('slug'));

        return [
            'title' => (string) $record->getAttribute($searchable['title_column']),
            'type' => $searchable['type'],
            'url' => $url,
            'subtitle' => parse_url($url, PHP_URL_PATH) ?: $url,
        ];
    }

    private function moduleIsEnabled(?string $method): bool
    {
        return ! $method || ! method_exists($this->modules, $method) || $this->modules->{$method}();
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
