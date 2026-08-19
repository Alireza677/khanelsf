<?php

namespace Tests\Feature;

use App\CMS\Collections\Blog\BlogCollectionAdapter;
use App\CMS\Collections\Data\CollectionItem;
use App\Models\Category;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlogCollectionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_maps_real_post_fields_without_queries_or_blog_specific_dto(): void
    {
        $category = Category::factory()->create(['title' => 'فناوری']);
        $post = Post::factory()->published()->for($category)->create([
            'title' => 'نوشته نمونه', 'slug' => 'sample-post', 'excerpt' => 'خلاصه نوشته',
            'published_at' => '2026-08-01 12:00:00',
        ]);
        $post->media()->create([
            'collection_name' => 'featured_image', 'name' => 'cover', 'file_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg', 'disk' => 'public', 'conversions_disk' => 'public', 'size' => 1,
            'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [],
            'responsive_images' => [], 'order_column' => 1,
        ]);
        $post->load(['category', 'media']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $item = app(BlogCollectionAdapter::class)->item($post);

        $this->assertSame([], DB::getQueryLog());
        $this->assertInstanceOf(CollectionItem::class, $item);
        $this->assertNotInstanceOf(Post::class, $item);
        $this->assertSame('نوشته نمونه', $item->title);
        $this->assertSame('خلاصه نوشته', $item->excerpt);
        $this->assertSame(['فناوری'], $item->badges);
        $this->assertSame('تاریخ انتشار', $item->metaItems[0]->label);
        $this->assertSame($post->published_at->toFormattedDateString(), $item->metaItems[0]->value);
        $this->assertSame(route('blog.show', 'sample-post', absolute: false), $item->action?->href);
        $this->assertStringContainsString('cover.jpg', $item->image?->url ?? '');
    }

    public function test_main_archive_uses_shared_collection_and_preserves_publication_rules(): void
    {
        $category = Category::factory()->create(['title' => 'مقالات']);
        Post::factory()->published()->for($category)->create(['title' => 'نوشته عمومی']);
        Post::factory()->draft()->for($category)->create(['title' => 'نوشته پیش‌نویس']);
        Post::factory()->published()->for($category)->create([
            'title' => 'نوشته آینده', 'published_at' => now()->addDay(),
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('shared-collection shared-collection--clean_grid', false)
            ->assertSee('shared-collection-card', false)
            ->assertSee('نوشته عمومی')
            ->assertSee(route('blog.search', absolute: false), false)
            ->assertDontSee('نوشته پیش‌نویس')
            ->assertDontSee('نوشته آینده')
            ->assertDontSee('class="blog-card"', false);
    }

    public function test_shared_grid_handles_zero_one_two_three_and_five_posts_with_nullable_fields(): void
    {
        foreach ([0, 1, 2, 3, 5] as $count) {
            $posts = collect($count > 0 ? range(1, $count) : [])->map(function (int $index): Post {
                $post = new Post([
                    'title' => $index === 1 ? str_repeat('عنوان طولانی ', 20) : "Post {$index}",
                    'slug' => "post-{$index}", 'excerpt' => $index % 2 === 0 ? null : str_repeat('خلاصه ', 30),
                    'published_at' => null,
                ]);
                $post->setRelation('category', null);
                $post->setRelation('media', collect());

                return $post;
            });
            $paginator = new LengthAwarePaginator($posts, $count, 12, 1, ['path' => '/blog']);
            $collection = app(BlogCollectionAdapter::class)->adapt($paginator);
            $html = view('partials.presentations.collection', compact('collection'))->render();

            $this->assertSame($count, substr_count($html, 'class="shared-collection-card"'));
            $this->assertStringNotContainsString('App\\Models\\Post', $html);
            if ($count === 0) {
                $this->assertStringContainsString('shared-collection__empty', $html);
            } else {
                $this->assertStringContainsString('shared-collection__grid--3', $html);
                $this->assertStringNotContainsString('shared-collection__empty', $html);
            }
        }
    }

    public function test_pagination_maps_to_shared_contract_and_category_archive_uses_it(): void
    {
        $posts = collect(range(1, 12))->map(function (int $index): Post {
            $post = new Post(['title' => "Post {$index}", 'slug' => "post-{$index}"]);
            $post->setRelation('category', null);
            $post->setRelation('media', collect());

            return $post;
        });
        $collection = app(BlogCollectionAdapter::class)->adapt(
            new LengthAwarePaginator($posts, 13, 12, 1, ['path' => '/blog']),
        );

        $this->assertSame(2, $collection->pagination?->lastPage);
        $this->assertSame('/blog?page=2', $collection->pagination?->nextUrl);

        $category = Category::factory()->create(['title' => 'دسته مستقل', 'slug' => 'independent-category']);
        Post::factory()->published()->for($category)->create(['title' => 'نوشته دسته']);
        $this->get(route('blog.category', $category->slug))
            ->assertOk()
            ->assertSee('نوشته دسته')
            ->assertSee('shared-collection shared-collection--clean_grid', false)
            ->assertDontSee('class="blog-card"', false);
    }

    public function test_category_archive_preserves_context_lifecycle_empty_state_and_pagination(): void
    {
        $category = Category::factory()->create([
            'title' => 'دسته معماری', 'slug' => 'architecture', 'description' => 'توضیح دسته معماری',
        ]);
        Post::factory()->count(13)->published()->for($category)->create();
        Post::factory()->draft()->for($category)->create(['title' => 'پیش‌نویس مخفی']);
        Post::factory()->published()->for($category)->create([
            'title' => 'آینده مخفی', 'published_at' => now()->addDay(),
        ]);

        $firstPage = $this->get(route('blog.category', $category->slug))
            ->assertOk()
            ->assertSee('دسته معماری')
            ->assertSee('توضیح دسته معماری')
            ->assertSee('shared-collection__grid--3', false)
            ->assertSee('?page=2', false)
            ->assertDontSee('پیش‌نویس مخفی')
            ->assertDontSee('آینده مخفی');
        $this->assertSame(12, substr_count($firstPage->getContent(), 'class="shared-collection-card"'));

        $secondPage = $this->get(route('blog.category', [$category->slug, 'page' => 2]))->assertOk();
        $this->assertSame(1, substr_count($secondPage->getContent(), 'class="shared-collection-card"'));

        $empty = Category::factory()->create(['slug' => 'empty-category', 'description' => null]);
        $this->get(route('blog.category', $empty->slug))
            ->assertOk()
            ->assertSee('shared-collection__empty', false)
            ->assertSee('No posts have been published in this category yet.');

        $twoPosts = Category::factory()->create(['slug' => 'two-posts-category']);
        Post::factory()->count(2)->published()->for($twoPosts)->create();
        $twoPostsResponse = $this->get(route('blog.category', $twoPosts->slug))->assertOk();
        $this->assertSame(2, substr_count($twoPostsResponse->getContent(), 'class="shared-collection-card"'));

        $draftCategory = Category::factory()->draft()->create();
        $this->get(route('blog.category', $draftCategory->slug))->assertNotFound();
    }

    public function test_post_category_template_uses_canonical_collection_and_shared_cards(): void
    {
        $category = Category::factory()->create([
            'title' => 'دسته قالب', 'slug' => 'template-category', 'description' => 'شرح قالب دسته',
        ]);
        $post = Post::factory()->published()->for($category)->create([
            'title' => 'نوشته قالب', 'slug' => 'template-post',
        ]);
        Template::query()->create([
            'title' => 'قالب دسته', 'slug' => 'category-collection-template', 'type' => 'post_category',
            'status' => 'published', 'is_default' => true, 'priority' => 10,
            'conditions' => ['type' => 'all'],
            'blocks' => [
                ['type' => 'template_archive_header', 'data' => []],
                ['type' => 'template_content_grid', 'data' => []],
            ],
        ]);

        $this->get(route('blog.category', $category->slug))
            ->assertOk()
            ->assertSee('دسته قالب')
            ->assertSee('شرح قالب دسته')
            ->assertSee('shared-collection shared-collection--clean_grid', false)
            ->assertSee('class="shared-collection-card"', false)
            ->assertSee(route('blog.show', $post->slug, absolute: false), false)
            ->assertDontSee('class="blog-card"', false);
    }

    public function test_template_content_grid_keeps_legacy_model_input_compatible(): void
    {
        $category = Category::factory()->create();
        $post = Post::factory()->published()->for($category)->create(['title' => 'Legacy Post']);
        $post->setRelation('category', $category);
        $post->setRelation('media', collect());

        $html = view('partials.blocks.template_content_grid', [
            'data' => [],
            'context' => ['type' => 'posts', 'items' => collect([$post])],
        ])->render();

        $this->assertStringContainsString('class="blog-card"', $html);
        $this->assertStringContainsString('Legacy Post', $html);
        $this->assertStringNotContainsString('class="shared-collection-card"', $html);
    }

    public function test_category_archive_eager_loads_media_once_for_multiple_posts(): void
    {
        $category = Category::factory()->create();
        Post::factory()->count(5)->published()->for($category)->create();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('blog.category', $category->slug))->assertOk();

        $mediaQueries = array_filter($queries, fn (string $sql): bool => preg_match('/from\s+["`]?media["`]?/i', $sql) === 1);
        $this->assertCount(1, $mediaQueries);
    }

    public function test_category_seo_canonical_schema_and_sitemap_policy_are_preserved(): void
    {
        $category = Category::factory()->create([
            'title' => 'دسته سئو', 'slug' => 'seo-category', 'description' => 'توضیح سئو',
        ]);

        $this->get(route('blog.category', [$category->slug, 'page' => 2]))
            ->assertOk()
            ->assertSee('rel="canonical" href="'.route('blog.category', $category->slug).'"', false)
            ->assertSee('name="robots" content="index, follow"', false)
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertDontSee('rel="canonical" href="'.route('blog.category', [$category->slug, 'page' => 2]).'"', false);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('blog.category', $category->slug), false);
    }

    public function test_search_uses_shared_collection_and_preserves_matching_and_lifecycle_semantics(): void
    {
        $category = Category::factory()->create(['title' => 'دانش']);
        $titleMatch = Post::factory()->published()->for($category)->create([
            'title' => 'Needle in title', 'slug' => 'title-match', 'excerpt' => 'خلاصه نتیجه',
        ]);
        $titleMatch->media()->create([
            'collection_name' => 'featured_image', 'name' => 'search-cover', 'file_name' => 'search-cover.jpg',
            'mime_type' => 'image/jpeg', 'disk' => 'public', 'conversions_disk' => 'public', 'size' => 1,
            'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [],
            'responsive_images' => [], 'order_column' => 1,
        ]);
        Post::factory()->published()->for($category)->create([
            'title' => 'Excerpt match', 'excerpt' => 'needle appears here', 'content' => 'none',
        ]);
        Post::factory()->published()->for($category)->create([
            'title' => 'Content match', 'excerpt' => null, 'content' => '<p>needle in body</p>',
        ]);
        Post::factory()->published()->for($category)->create([
            'title' => 'Unrelated public post', 'excerpt' => 'other', 'content' => 'other',
        ]);
        Post::factory()->draft()->for($category)->create(['title' => 'Needle draft']);
        Post::factory()->published()->for($category)->create([
            'title' => 'Needle future', 'published_at' => now()->addDay(),
        ]);

        $response = $this->get(route('blog.search', ['q' => 'needle']))
            ->assertOk()
            ->assertSee('shared-collection shared-collection--clean_grid', false)
            ->assertSee('Search results for &quot;needle&quot;', false)
            ->assertSee('value="needle"', false)
            ->assertSee('Needle in title')
            ->assertSee('Excerpt match')
            ->assertSee('Content match')
            ->assertSee('خلاصه نتیجه')
            ->assertSee('دانش')
            ->assertSee('search-cover.jpg', false)
            ->assertSee(route('blog.show', 'title-match', absolute: false), false)
            ->assertDontSee('Unrelated public post')
            ->assertDontSee('Needle draft')
            ->assertDontSee('Needle future')
            ->assertDontSee('class="blog-card"', false);
        $this->assertSame(3, substr_count($response->getContent(), 'class="shared-collection-card"'));

        $this->get(route('blog.search'))
            ->assertOk()
            ->assertSee('Search')
            ->assertSee('Needle in title')
            ->assertSee('Unrelated public post')
            ->assertDontSee('Needle draft')
            ->assertDontSee('Needle future');

        $this->get(route('blog.search', ['q' => 'no-results']))
            ->assertOk()
            ->assertSee('shared-collection__empty', false)
            ->assertSee('No matching posts were found.');
    }

    public function test_search_pagination_preserves_query_and_uses_twelve_items_per_page(): void
    {
        $category = Category::factory()->create();
        Post::factory()->count(13)->published()->for($category)->create([
            'excerpt' => 'pagination-keyword',
        ]);

        $firstPage = $this->get(route('blog.search', ['q' => 'pagination-keyword']))->assertOk();
        $this->assertSame(12, substr_count($firstPage->getContent(), 'class="shared-collection-card"'));
        $firstPage->assertSee('q=pagination-keyword', false)->assertSee('page=2', false);

        $secondPage = $this->get(route('blog.search', ['q' => 'pagination-keyword', 'page' => 2]))->assertOk();
        $this->assertSame(1, substr_count($secondPage->getContent(), 'class="shared-collection-card"'));
        $secondPage->assertSee('value="pagination-keyword"', false);
    }

    public function test_search_seo_remains_noindex_with_query_canonical_without_page_or_schema(): void
    {
        $url = route('blog.search', ['q' => 'canonical-query']);
        $pagedUrl = route('blog.search', ['q' => 'canonical-query', 'page' => 2]);

        $this->get($pagedUrl)
            ->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false)
            ->assertSee('rel="canonical" href="'.$url.'"', false)
            ->assertDontSee('rel="canonical" href="'.$pagedUrl.'"', false)
            ->assertDontSee('application/ld+json', false);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee('/blog/search', false);
    }

    public function test_search_eager_loads_media_once_for_multiple_results(): void
    {
        $category = Category::factory()->create();
        Post::factory()->count(5)->published()->for($category)->create(['excerpt' => 'media-query']);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('blog.search', ['q' => 'media-query']))->assertOk();

        $mediaQueries = array_filter($queries, fn (string $sql): bool => preg_match('/from\s+["`]?media["`]?/i', $sql) === 1);
        $this->assertCount(1, $mediaQueries);
    }

    public function test_search_http_card_counts_are_stable_for_zero_one_two_and_twelve_results(): void
    {
        $category = Category::factory()->create();

        foreach ([0, 1, 2, 12] as $count) {
            $term = "exact-count-{$count}";
            if ($count > 0) {
                Post::factory()->count($count)->published()->for($category)->create(['excerpt' => $term]);
            }

            $response = $this->get(route('blog.search', ['q' => $term]))->assertOk();
            $this->assertSame($count, substr_count($response->getContent(), 'class="shared-collection-card"'));
            $response->assertSee('shared-collection shared-collection--clean_grid', false)
                ->assertDontSee('class="blog-card"', false);
        }
    }

    public function test_blog_index_template_uses_canonical_collection_without_duplicate_header(): void
    {
        $category = Category::factory()->create(['title' => 'دسته قالب اصلی']);
        $post = Post::factory()->published()->for($category)->create([
            'title' => 'نوشته قالب اصلی', 'slug' => 'main-template-post', 'excerpt' => 'خلاصه قالب اصلی',
        ]);
        $post->media()->create([
            'collection_name' => 'featured_image', 'name' => 'main-cover', 'file_name' => 'main-cover.jpg',
            'mime_type' => 'image/jpeg', 'disk' => 'public', 'conversions_disk' => 'public', 'size' => 1,
            'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [],
            'responsive_images' => [], 'order_column' => 1,
        ]);
        Template::query()->create([
            'title' => 'قالب اصلی وبلاگ', 'slug' => 'canonical-blog-index', 'type' => 'blog_index',
            'status' => 'published', 'is_default' => true, 'priority' => 10,
            'conditions' => ['type' => 'all'],
            'blocks' => [
                ['type' => 'template_archive_header', 'data' => []],
                ['type' => 'template_content_grid', 'data' => []],
            ],
        ]);

        $response = $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('archive-header', false)
            ->assertSee('shared-collection shared-collection--clean_grid', false)
            ->assertSee('class="shared-collection-card"', false)
            ->assertSee('نوشته قالب اصلی')
            ->assertSee('خلاصه قالب اصلی')
            ->assertSee('دسته قالب اصلی')
            ->assertSee('main-cover.jpg', false)
            ->assertSee(route('blog.show', 'main-template-post', absolute: false), false)
            ->assertDontSee('class="blog-card"', false)
            ->assertDontSee('shared-collection__header', false);

        $this->assertSame(1, substr_count($response->getContent(), 'archive-header__content'));
    }

    public function test_blog_index_template_handles_zero_one_two_and_thirteen_posts_with_pagination(): void
    {
        $category = Category::factory()->create();
        Template::query()->create([
            'title' => 'قالب شمارش وبلاگ', 'slug' => 'blog-index-counts', 'type' => 'blog_index',
            'status' => 'published', 'is_default' => true, 'priority' => 10,
            'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'template_content_grid', 'data' => []]],
        ]);

        foreach ([0, 1, 2, 13] as $count) {
            Post::query()->delete();
            if ($count > 0) {
                Post::factory()->count($count)->published()->for($category)->create();
            }

            $firstPage = $this->get(route('blog.index'))->assertOk();
            $this->assertSame(min($count, 12), substr_count($firstPage->getContent(), 'class="shared-collection-card"'));
            $firstPage->assertSee('shared-collection shared-collection--clean_grid', false)
                ->assertDontSee('class="blog-card"', false);

            if ($count === 0) {
                $firstPage->assertSee('shared-collection__empty', false);
            }

            if ($count === 13) {
                $firstPage->assertSee('page=2', false);
                $secondPage = $this->get(route('blog.index', ['page' => 2]))->assertOk();
                $this->assertSame(1, substr_count($secondPage->getContent(), 'class="shared-collection-card"'));
            }
        }
    }
}
