<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use Illuminate\Support\Collection;

class SitemapService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ServiceQueryService $services,
    ) {}

    public function urls(): Collection
    {
        $urls = collect();

        if (! filter_var($this->settings->get('sitemap_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return $urls;
        }

        $homePage = Page::query()
            ->published()
            ->where('slug', 'home')
            ->first();
        $contactPage = Page::query()
            ->published()
            ->where('slug', 'contact')
            ->first();

        if (! $homePage || $homePage->robots_index) {
            $this->add($urls, route('home'));
        }

        $this->add($urls, route('blog.index'));

        if (! $contactPage || $contactPage->robots_index) {
            $this->add($urls, route('contact.create'));
        }

        if (filter_var($this->settings->get('projects_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->add($urls, route('projects.index'));
        }

        if (
            filter_var($this->settings->get('projects_enabled', true), FILTER_VALIDATE_BOOLEAN)
            && filter_var($this->settings->get('galleries_enabled', true), FILTER_VALIDATE_BOOLEAN)
        ) {
            $this->add($urls, route('galleries.index'));
        }

        if (filter_var($this->settings->get('shop_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $this->add($urls, route('shop.index'));
        }

        $this->add($urls, route('services.index'));

        Page::query()
            ->published()
            ->where('robots_index', true)
            ->whereNotIn('slug', ['home', 'contact'])
            ->latest('updated_at')
            ->get()
            ->each(fn (Page $page) => $this->add(
                $urls,
                route('pages.show', $page->slug),
                $page->updated_at?->toAtomString(),
            ));

        Category::query()
            ->published()
            ->latest('updated_at')
            ->get()
            ->each(fn (Category $category) => $this->add(
                $urls,
                route('blog.category', $category->slug),
                $category->updated_at?->toAtomString(),
            ));

        Post::query()
            ->published()
            ->where('robots_index', true)
            ->latest('updated_at')
            ->get()
            ->each(fn (Post $post) => $this->add(
                $urls,
                route('blog.show', $post->slug),
                ($post->updated_at ?: $post->published_at)?->toAtomString(),
            ));

        if (filter_var($this->settings->get('projects_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            ProjectCategory::query()
                ->active()
                ->where('robots_index', true)
                ->latest('updated_at')
                ->get()
                ->each(fn (ProjectCategory $category) => $this->add(
                    $urls,
                    route('projects.category', $category->slug),
                    $category->updated_at?->toAtomString(),
                ));

            Project::query()
                ->published()
                ->where('robots_index', true)
                ->latest('updated_at')
                ->get()
                ->each(fn (Project $project) => $this->add(
                    $urls,
                    route('projects.show', $project->slug),
                    ($project->updated_at ?: $project->published_at)?->toAtomString(),
                ));
        }

        if (filter_var($this->settings->get('shop_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            ProductCategory::query()
                ->active()
                ->where('robots_index', true)
                ->latest('updated_at')
                ->get()
                ->each(fn (ProductCategory $category) => $this->add(
                    $urls,
                    route('shop.category', $category->slug),
                    $category->updated_at?->toAtomString(),
                ));

            Product::query()
                ->published()
                ->where('robots_index', true)
                ->latest('updated_at')
                ->get()
                ->each(fn (Product $product) => $this->add(
                    $urls,
                    route('shop.show', $product->slug),
                    ($product->updated_at ?: $product->published_at)?->toAtomString(),
                ));
        }

        $this->services->archiveQuery()
            ->latest('updated_at')
            ->get()
            ->each(fn (Service $service) => $this->add(
                $urls,
                route('services.show', $service->slug),
                ($service->updated_at ?: $service->published_at)?->toAtomString(),
            ));

        return $urls->values();
    }

    private function add(Collection $urls, string $loc, ?string $lastmod = null): void
    {
        if ($urls->contains(fn (array $url): bool => $url['loc'] === $loc)) {
            return;
        }

        $urls->push([
            'loc' => $loc,
            'lastmod' => $lastmod,
        ]);
    }

    public function xml(): string
    {
        $items = $this->urls()
            ->map(function (array $url): string {
                $lastmod = $url['lastmod']
                    ? "\n        <lastmod>".e($url['lastmod']).'</lastmod>'
                    : '';

                return "    <url>\n        <loc>".e($url['loc'])."</loc>{$lastmod}\n    </url>";
            })
            ->implode("\n");

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$items}
</urlset>
XML;
    }
}
