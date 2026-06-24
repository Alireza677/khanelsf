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
use App\Support\SeoData;
use Illuminate\Support\Str;

class SeoService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function forPage(Page $page, ?string $canonicalUrl = null, string $ogType = 'website'): SeoData
    {
        $description = $page->seo_description
            ?: Str::limit(strip_tags((string) $page->content), 160, '');

        return $this->make([
            'title' => $page->seo_title ?: $page->title,
            'description' => $description,
            'canonical_url' => $canonicalUrl ?: route($page->slug === 'home' ? 'home' : 'pages.show', $page->slug === 'home' ? [] : ['slug' => $page->slug]),
            'robots_index' => $page->robots_index,
            'robots_follow' => $page->robots_follow,
            'og_image' => $page->seo_image ?: $page->featuredImageUrl(),
            'og_type' => $ogType,
            'schema' => $this->webPageSchema($page, $canonicalUrl),
        ]);
    }

    public function forPost(Post $post): SeoData
    {
        $description = $post->seo_description
            ?: $post->excerpt
            ?: Str::limit(strip_tags((string) $post->content), 160, '');

        return $this->make([
            'title' => $post->seo_title ?: $post->title,
            'description' => $description,
            'canonical_url' => route('blog.show', $post->slug),
            'robots_index' => $post->robots_index,
            'robots_follow' => $post->robots_follow,
            'og_image' => $post->seo_image ?: $post->featuredImageUrl(),
            'og_type' => 'article',
            'schema' => $this->articleSchema($post),
        ]);
    }

    public function forBlogIndex(): SeoData
    {
        return $this->make([
            'title' => $this->settings->get('blog_seo_title', 'Blog'),
            'description' => $this->settings->get('blog_seo_description', 'Latest articles and updates.'),
            'canonical_url' => route('blog.index'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Blog',
                'name' => $this->settings->get('blog_seo_title', 'Blog'),
                'url' => route('blog.index'),
            ],
        ]);
    }

    public function forBlogCategory(Category $category): SeoData
    {
        return $this->make([
            'title' => $category->title,
            'description' => $category->description ?: 'Articles filed under '.$category->title.'.',
            'canonical_url' => route('blog.category', $category->slug),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $category->title,
                'url' => route('blog.category', $category->slug),
            ],
        ]);
    }

    public function forBlogSearch(?string $query = null): SeoData
    {
        return $this->make([
            'title' => filled($query) ? 'Search results for '.$query : 'Search',
            'description' => 'Search published articles.',
            'canonical_url' => route('blog.search', filled($query) ? ['q' => $query] : []),
            'robots_index' => false,
            'robots_follow' => true,
        ]);
    }

    public function forProject(Project $project): SeoData
    {
        $description = $project->seo_description
            ?: $project->excerpt
            ?: Str::limit(strip_tags((string) $project->content), 160, '');

        return $this->make([
            'title' => $project->seo_title ?: $project->title,
            'description' => $description,
            'canonical_url' => route('projects.show', $project->slug),
            'robots_index' => $project->robots_index,
            'robots_follow' => $project->robots_follow,
            'og_image' => $project->seo_image ?: $project->featuredImageUrl(),
            'og_type' => 'article',
            'schema' => $this->projectSchema($project, $description),
        ]);
    }

    public function forProjectIndex(): SeoData
    {
        return $this->make([
            'title' => $this->settings->get('projects_seo_title', $this->settings->get('projects_index_title', 'Projects')),
            'description' => $this->settings->get('projects_seo_description', $this->settings->get('projects_index_description', 'Selected work and case studies.')),
            'canonical_url' => route('projects.index'),
            'og_image' => $this->settings->assetUrl($this->settings->get('projects_og_image')),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $this->settings->get('projects_index_title', 'Projects'),
                'url' => route('projects.index'),
            ],
        ]);
    }

    public function forProjectCategory(ProjectCategory $category): SeoData
    {
        return $this->make([
            'title' => $category->seo_title ?: $category->name,
            'description' => $category->seo_description ?: $category->description,
            'canonical_url' => route('projects.category', $category->slug),
            'robots_index' => $category->robots_index,
            'robots_follow' => $category->robots_follow,
            'og_image' => $category->seo_image,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $category->name,
                'url' => route('projects.category', $category->slug),
            ],
        ]);
    }

    public function forGallery(Gallery $gallery): SeoData
    {
        $description = $gallery->seo_description
            ?: $gallery->excerpt
            ?: Str::limit(strip_tags((string) $gallery->content), 160, '');

        return $this->make([
            'title' => $gallery->seo_title ?: $gallery->title,
            'description' => $description,
            'canonical_url' => route('galleries.show', $gallery->slug),
            'robots_index' => $gallery->robots_index,
            'robots_follow' => $gallery->robots_follow,
            'og_image' => $gallery->seo_image ?: $gallery->cardImageUrl(),
            'og_type' => 'article',
            'schema' => $this->gallerySchema($gallery, $description),
        ]);
    }

    public function forGalleryIndex(): SeoData
    {
        return $this->make([
            'title' => $this->settings->get('galleries_seo_title', $this->settings->get('galleries_index_title', 'Galleries')),
            'description' => $this->settings->get('galleries_seo_description', $this->settings->get('galleries_index_description', 'Browse image and video galleries.')),
            'canonical_url' => route('galleries.index'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $this->settings->get('galleries_index_title', 'Galleries'),
                'url' => route('galleries.index'),
            ],
        ]);
    }

    public function forGalleryCategory(GalleryCategory $category): SeoData
    {
        return $this->make([
            'title' => $category->seo_title ?: $category->name,
            'description' => $category->seo_description ?: $category->description,
            'canonical_url' => route('galleries.category', $category->slug),
            'robots_index' => $category->robots_index,
            'robots_follow' => $category->robots_follow,
            'og_image' => $category->seo_image,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $category->name,
                'url' => route('galleries.category', $category->slug),
            ],
        ]);
    }

    public function forProduct(Product $product): SeoData
    {
        $description = $product->seo_description
            ?: $product->excerpt
            ?: Str::limit(strip_tags((string) $product->content), 160, '');

        return $this->make([
            'title' => $product->seo_title ?: $product->title,
            'description' => $description,
            'canonical_url' => route('shop.show', $product->slug),
            'robots_index' => $product->robots_index,
            'robots_follow' => $product->robots_follow,
            'og_image' => $product->seo_image ?: $product->featuredImageUrl(),
            'og_type' => 'product',
            'schema' => $this->productSchema($product, $description),
        ]);
    }

    public function forShopIndex(): SeoData
    {
        return $this->make([
            'title' => $this->settings->get('shop_seo_title', $this->settings->get('shop_index_title', 'فروشگاه')),
            'description' => $this->settings->get('shop_seo_description', $this->settings->get('shop_index_description', 'Browse available products.')),
            'canonical_url' => route('shop.index'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $this->settings->get('shop_index_title', 'فروشگاه'),
                'url' => route('shop.index'),
            ],
        ]);
    }

    public function forProductCategory(ProductCategory $category): SeoData
    {
        return $this->make([
            'title' => $category->seo_title ?: $category->name,
            'description' => $category->seo_description ?: $category->description,
            'canonical_url' => route('shop.category', $category->slug),
            'robots_index' => $category->robots_index,
            'robots_follow' => $category->robots_follow,
            'og_image' => $category->seo_image,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $category->name,
                'url' => route('shop.category', $category->slug),
            ],
        ]);
    }

    public function forContact(?Page $page = null): SeoData
    {
        if ($page) {
            return $this->forPage($page, route('contact.create'));
        }

        return $this->make([
            'title' => $this->settings->get('contact_seo_title', 'تماس با ما'),
            'description' => $this->settings->get('contact_seo_description', 'برای پرسش‌ها یا جزئیات پروژه با ما تماس بگیرید.'),
            'canonical_url' => route('contact.create'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'ContactPage',
                'name' => 'تماس با ما',
                'url' => route('contact.create'),
            ],
        ]);
    }

    public function make(array $data = []): SeoData
    {
        $title = filled($data['title'] ?? null)
            ? (string) $data['title']
            : $this->settings->siteTitle();

        $description = filled($data['description'] ?? null)
            ? (string) $data['description']
            : $this->settings->defaultMetaDescription();

        $canonicalUrl = $this->absoluteUrl($data['canonical_url'] ?? url()->current());
        $ogImage = $this->absoluteUrl($data['og_image'] ?? $this->settings->assetUrl($this->settings->get('default_og_image')));

        return new SeoData(
            title: $title,
            description: filled($description) ? $description : null,
            canonicalUrl: $canonicalUrl,
            robots: $this->robots(
                $data['robots_index'] ?? true,
                $data['robots_follow'] ?? true,
            ),
            ogTitle: $data['og_title'] ?? $title,
            ogDescription: $data['og_description'] ?? (filled($description) ? $description : null),
            ogImage: $ogImage,
            ogType: $data['og_type'] ?? 'website',
            twitterCard: $data['twitter_card'] ?? ($ogImage ? 'summary_large_image' : 'summary'),
            schema: $data['schema'] ?? null,
        );
    }

    private function robots(bool|int|string|null $index, bool|int|string|null $follow): string
    {
        return ($this->truthy($index) ? 'index' : 'noindex').', '.($this->truthy($follow) ? 'follow' : 'nofollow');
    }

    private function truthy(bool|int|string|null $value): bool
    {
        return filter_var($value ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    private function absoluteUrl(mixed $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $url = (string) $url;

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        return url($url);
    }

    private function webPageSchema(Page $page, ?string $canonicalUrl = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => $page->slug === 'contact' ? 'ContactPage' : 'WebPage',
            'name' => $page->title,
            'url' => $canonicalUrl ?: ($page->slug === 'home' ? route('home') : route('pages.show', $page->slug)),
        ];
    }

    private function articleSchema(Post $post): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->seo_description ?: $post->excerpt,
            'image' => $this->absoluteUrl($post->seo_image ?: $post->featuredImageUrl()),
            'datePublished' => $post->published_at?->toAtomString(),
            'dateModified' => $post->updated_at?->toAtomString(),
            'mainEntityOfPage' => route('blog.show', $post->slug),
        ];
    }

    private function projectSchema(Project $project, ?string $description = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CreativeWork',
            'name' => $project->title,
            'description' => $description,
            'image' => $this->absoluteUrl($project->seo_image ?: $project->featuredImageUrl()),
            'datePublished' => $project->published_at?->toAtomString(),
            'dateModified' => $project->updated_at?->toAtomString(),
            'url' => route('projects.show', $project->slug),
        ];
    }

    private function productSchema(Product $product, ?string $description = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->title,
            'description' => $description,
            'sku' => $product->sku,
            'image' => $this->absoluteUrl($product->seo_image ?: $product->featuredImageUrl()),
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format($product->currentPrice(), 2, '.', ''),
                'priceCurrency' => 'USD',
                'availability' => $product->isPurchasable()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => route('shop.show', $product->slug),
            ],
        ];
    }

    private function gallerySchema(Gallery $gallery, ?string $description = null): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => $gallery->type === 'video' ? 'VideoObject' : 'ImageGallery',
            'name' => $gallery->title,
            'description' => $description,
            'thumbnailUrl' => $this->absoluteUrl($gallery->cardImageUrl()),
            'uploadDate' => $gallery->published_at?->toAtomString(),
            'dateModified' => $gallery->updated_at?->toAtomString(),
            'url' => route('galleries.show', $gallery->slug),
        ];
    }
}
