<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Redirect;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ModuleRedirectSuggestionService
{
    public function projectPaths(): Collection
    {
        return collect(['/projects'])
            ->merge(ProjectCategory::query()->pluck('slug')->map(fn (string $slug): string => "/projects/category/{$slug}"))
            ->merge(Project::query()->pluck('slug')->map(fn (string $slug): string => "/projects/{$slug}"))
            ->unique()
            ->values();
    }

    public function shopPaths(): Collection
    {
        return collect(['/shop', '/cart', '/checkout'])
            ->merge(ProductCategory::query()->pluck('slug')->map(fn (string $slug): string => "/shop/category/{$slug}"))
            ->merge(Product::query()->pluck('slug')->map(fn (string $slug): string => "/shop/{$slug}"))
            ->unique()
            ->values();
    }

    public function galleryPaths(): Collection
    {
        return collect(['/galleries'])
            ->merge(GalleryCategory::query()->pluck('slug')->map(fn (string $slug): string => "/galleries/category/{$slug}"))
            ->merge(Gallery::query()->pluck('slug')->map(fn (string $slug): string => "/galleries/{$slug}"))
            ->unique()
            ->values();
    }

    public function createProjectRedirects(string $targetUrl = '/', int $statusCode = 301): int
    {
        return $this->createRedirects($this->projectPaths(), $targetUrl, $statusCode, 'Created from Projects module disable redirect suggestions.');
    }

    public function createShopRedirects(string $targetUrl = '/', int $statusCode = 301): int
    {
        return $this->createRedirects($this->shopPaths(), $targetUrl, $statusCode, 'Created from Shop module disable redirect suggestions.');
    }

    public function createGalleryRedirects(string $targetUrl = '/', int $statusCode = 301): int
    {
        return $this->createRedirects($this->galleryPaths(), $targetUrl, $statusCode, 'Created from Gallery module disable redirect suggestions.');
    }

    private function createRedirects(Collection $paths, string $targetUrl, int $statusCode, string $note): int
    {
        return DB::transaction(function () use ($paths, $targetUrl, $statusCode, $note): int {
            $count = 0;

            foreach ($paths as $path) {
                $sourcePath = Redirect::normalizePath($path);
                $targetPath = (str_starts_with($targetUrl, 'http://') || str_starts_with($targetUrl, 'https://'))
                    ? (parse_url($targetUrl, PHP_URL_PATH) ?: '/')
                    : Redirect::normalizePath($targetUrl);

                if ($sourcePath === $targetPath) {
                    continue;
                }

                Redirect::query()->updateOrCreate(
                    ['source_path' => $sourcePath],
                    [
                        'target_url' => $targetUrl,
                        'status_code' => in_array($statusCode, [301, 302], true) ? $statusCode : 301,
                        'is_active' => true,
                        'note' => $note,
                    ],
                );

                $count++;
            }

            return $count;
        });
    }
}
