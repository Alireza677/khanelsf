<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoSitemapRobotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_post_project_and_product_render_basic_seo_tags(): void
    {
        $page = Page::factory()->published()->create([
            'slug' => 'seo-page',
            'title' => 'SEO Page',
            'seo_description' => 'SEO page description.',
        ]);

        $post = Post::factory()->published()->create([
            'slug' => 'seo-post',
            'title' => 'SEO Post',
            'seo_description' => 'SEO post description.',
        ]);

        $project = Project::factory()->published()->create([
            'slug' => 'seo-project',
            'title' => 'SEO Project',
            'seo_description' => 'SEO project description.',
        ]);

        $product = Product::factory()->published()->create([
            'slug' => 'seo-product',
            'title' => 'SEO Product',
            'seo_description' => 'SEO product description.',
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('name="robots"', false)
            ->assertSee('property="og:title"', false);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('name="robots"', false)
            ->assertSee('property="og:type" content="article"', false);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('name="robots"', false)
            ->assertSee('property="og:title"', false);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('name="robots"', false)
            ->assertSee('property="og:title"', false);
    }

    public function test_sitemap_includes_published_content_and_excludes_drafts(): void
    {
        Page::factory()->published()->create(['slug' => 'published-page']);
        Page::factory()->draft()->create(['slug' => 'draft-page']);

        Category::factory()->create(['slug' => 'news']);
        Post::factory()->published()->create(['slug' => 'published-post']);
        Post::factory()->draft()->create(['slug' => 'draft-post']);

        ProjectCategory::factory()->create(['slug' => 'case-studies']);
        Project::factory()->published()->create(['slug' => 'published-project']);
        Project::factory()->draft()->create(['slug' => 'draft-project']);

        ProductCategory::factory()->create(['slug' => 'products']);
        Product::factory()->published()->create(['slug' => 'published-product']);
        Product::factory()->draft()->create(['slug' => 'draft-product']);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('pages.show', 'published-page'), false)
            ->assertSee(route('blog.show', 'published-post'), false)
            ->assertSee(route('projects.show', 'published-project'), false)
            ->assertSee(route('shop.show', 'published-product'), false)
            ->assertDontSee('draft-page')
            ->assertDontSee('draft-post')
            ->assertDontSee('draft-project')
            ->assertDontSee('draft-product');
    }

    public function test_sitemap_excludes_noindex_pages_posts_and_reserved_pages(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'robots_index' => false,
        ]);
        Page::factory()->published()->create([
            'slug' => 'contact',
            'robots_index' => false,
        ]);
        Page::factory()->published()->create([
            'slug' => 'noindex-page',
            'robots_index' => false,
        ]);
        Post::factory()->published()->create([
            'slug' => 'noindex-post',
            'robots_index' => false,
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee('<loc>'.e(route('home')).'</loc>', false)
            ->assertDontSee('<loc>'.e(route('contact.create')).'</loc>', false)
            ->assertDontSee('noindex-page')
            ->assertDontSee('noindex-post');
    }

    public function test_robots_txt_loads_with_sitemap_url(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertSee('Sitemap: '.route('sitemap'));
    }
}
