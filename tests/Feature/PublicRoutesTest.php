<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'seo_description' => 'Homepage SEO description.',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Home')
            ->assertSee('rel="canonical"', false)
            ->assertSee('name="robots"', false);
    }

    public function test_frontend_layout_includes_the_canonical_custom_font_styles(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/fonts/site.woff2', 'font-data');
        Page::factory()->published()->create(['slug' => 'home', 'title' => 'Home']);

        $this->setting('font_family', 'custom', 'theme', 'select');
        $this->setting('custom_font_name', 'Site Font', 'theme', 'text');
        $this->setting('custom_font_file', 'settings/fonts/site.woff2', 'theme', 'file');

        $response = $this->get('/')->assertOk();

        $response->assertSee('data-site-font', false);
        $response->assertSee('@font-face', false);
        $response->assertSee('--site-font-family:"Site Font"', false);
        $response->assertSee('/storage/settings/fonts/site.woff2', false);
        $this->assertSame(1, substr_count($response->getContent(), '@font-face'));
    }

    public function test_homepage_head_contains_escaped_google_site_verification(): void
    {
        Page::factory()->published()->create(['slug' => 'home', 'title' => 'Home']);
        Setting::query()->create([
            'key' => 'google_site_verification',
            'value' => 'abc123&amp;quot;',
            'group' => 'seo',
            'type' => 'text',
        ]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('<meta name="google-site-verification" content="abc123&amp;amp;quot;">', false);
        $this->assertLessThan(
            strpos($response->getContent(), '</head>'),
            strpos($response->getContent(), 'name="google-site-verification"'),
        );
    }

    public function test_featured_module_blocks_do_not_break_when_modules_are_disabled(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'featured_projects',
                    'data' => ['section_title' => 'Featured Projects', 'button_label' => 'Projects', 'button_url' => '/projects'],
                ],
                [
                    'type' => 'featured_products',
                    'data' => ['section_title' => 'Featured Products', 'button_label' => 'Shop', 'button_url' => '/shop'],
                ],
                [
                    'type' => 'featured_galleries',
                    'data' => ['section_title' => 'Featured Galleries', 'button_label' => 'Galleries', 'button_url' => '/galleries'],
                ],
            ],
        ]);
        Project::factory()->published()->create(['title' => 'Hidden Featured Project', 'is_featured' => true]);
        Product::factory()->published()->create(['title' => 'Hidden Featured Product', 'is_featured' => true]);
        Gallery::factory()->featured()->create(['title' => 'Hidden Featured Gallery']);

        $this->setting('projects_enabled', '0', 'projects', 'boolean');
        $this->setting('shop_enabled', '0', 'shop', 'boolean');
        $this->setting('galleries_enabled', '0', 'galleries', 'boolean');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Hidden Featured Project')
            ->assertDontSee('Hidden Featured Product')
            ->assertDontSee('Hidden Featured Gallery')
            ->assertDontSee('/projects', false)
            ->assertDontSee('/shop', false)
            ->assertDontSee('/galleries', false);
    }

    public function test_hero_block_templates_render_and_default_stays_backward_compatible(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'template' => 'hero_1',
                        'eyebrow' => 'Hero 1 Label',
                        'title' => 'Hero One Title',
                        'subtitle' => 'Modern reusable hero section.',
                        'primary_button_label' => 'Start',
                        'primary_button_url' => '/contact',
                        'secondary_button_label' => 'Learn',
                        'secondary_button_url' => '/about',
                        'image' => 'https://example.com/hero.jpg',
                        'overlay_opacity' => 40,
                    ],
                ],
                [
                    'type' => 'hero',
                    'data' => [
                        'title' => 'Legacy Hero Title',
                        'subtitle' => 'Legacy subtitle',
                        'description' => 'Legacy hero still renders without a template key.',
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('hero-template-1', false)
            ->assertSee('Hero 1 Label')
            ->assertSee('Hero One Title')
            ->assertSee('Modern reusable hero section.')
            ->assertSee('Legacy Hero Title')
            ->assertSee('block-hero', false);
    }

    public function test_animated_dotted_surface_is_scoped_to_enabled_hero_blocks(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                ['type' => 'hero', 'data' => [
                    'template' => 'hero_1',
                    'hero_1_theme' => 'animated_dotted_surface',
                    'title' => 'Animated hero',
                    'animated_background_density' => 'high',
                    'animated_background_speed' => 'normal',
                    'animated_background_color' => '#123456',
                    'animated_dots_color' => '#abcdef',
                ]],
                ['type' => 'hero', 'data' => [
                    'template' => 'hero_1',
                    'hero_1_theme' => 'image',
                    'title' => 'Static hero',
                ]],
                ['type' => 'hero', 'data' => [
                    'template' => 'hero_1',
                    'hero_1_theme' => 'animated_dotted_surface',
                    'title' => 'Second animated hero',
                    'animated_background_density' => 'low',
                ]],
                ['type' => 'hero', 'data' => [
                    'template' => 'hero_1',
                    'hero_1_theme' => 'animated_dotted_surface',
                    'title' => 'Disabled animated hero',
                    'animated_background_enabled' => false,
                ]],
            ],
        ]);

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('hero-template-1--animated-dotted-surface', false)
            ->assertSee('data-hero-dotted-surface', false)
            ->assertSee('data-density="high"', false)
            ->assertSee('data-speed="normal"', false)
            ->assertSee('data-bg-color="#123456"', false)
            ->assertSee('data-dots-color="#abcdef"', false)
            ->assertSee('--hero-animated-background-color: #123456', false)
            ->assertSee('data-interactive="true"', false);

        $this->assertSame(2, substr_count($response->getContent(), 'data-hero-dotted-surface'));
    }

    public function test_animated_paths_background_renders_without_a_three_canvas(): void
    {
        $page = Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'template' => 'hero_1',
                        'hero_1_theme' => 'animated_paths',
                        'title' => 'Paths hero',
                        'paths_background_color' => '#123456',
                        'paths_color' => '#abcdef',
                        'paths_opacity' => 0.6,
                        'paths_speed' => 'fast',
                        'paths_density' => 'medium',
                        'paths_line_width' => 1.4,
                        'paths_animation_enabled' => true,
                    ],
                ],
                [
                    'type' => 'hero',
                    'data' => [
                        'template' => 'hero_1',
                        'hero_1_theme' => 'light_grid',
                        'title' => 'Grid hero',
                    ],
                ],
            ],
        ]);

        $this->assertSame('animated_paths', $page->fresh()->blocks[0]['data']['hero_1_theme']);

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('hero-template-1--animated-paths', false)
            ->assertSee('data-hero-animated-paths', false)
            ->assertSee('--hero-paths-background: #123456', false)
            ->assertSee('--hero-paths-color: #abcdef', false)
            ->assertSee('stroke="#abcdef"', false)
            ->assertSee('data-speed="fast"', false)
            ->assertSee('data-density="medium"', false)
            ->assertSee('data-animation-enabled="true"', false)
            ->assertSee('pathLength="1"', false)
            ->assertDontSee('data-hero-dotted-surface', false);

        $this->assertSame(1, substr_count($response->getContent(), 'data-hero-animated-paths'));
        $this->assertSame(72, substr_count($response->getContent(), 'pathLength="1"'));
    }

    public function test_hero_two_selector_template_renders(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'template' => 'hero_2',
                        'hero_2_alignment' => 'right',
                        'title' => "They Won't Be This Age Twice",
                        'subtitle' => "Tell us what you're looking for and we'll point you in the right direction.",
                        'primary_button_label' => 'Get Started',
                        'secondary_button_label' => 'Check out our Learning Center',
                        'secondary_button_url' => '/blog',
                        'selector_placeholder' => "I'm looking for...",
                        'selector_items' => [
                            ['label' => 'The Right Type of Pool', 'url' => '/projects'],
                            ['label' => 'An Instant Price Range', 'url' => '/contact'],
                        ],
                        'image' => 'https://example.com/pool.jpg',
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('hero-template-2 hero-template-2--right', false)
            ->assertSee("They Won't Be This Age Twice")
            ->assertSee("I'm looking for...")
            ->assertSee('The Right Type of Pool')
            ->assertSee('data-hero-template-2-button', false)
            ->assertSee('Check out our Learning Center');
    }

    public function test_custom_html_block_renders_raw_code(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'custom_html',
                    'data' => [
                        'code' => '<style>.custom-test{color:red}</style><div class="custom-test">Raw HTML Block</div><script>window.customHtmlBlock=true;</script>',
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('custom-html-block', false)
            ->assertSee('<style>.custom-test{color:red}</style>', false)
            ->assertSee('<div class="custom-test">Raw HTML Block</div>', false)
            ->assertSee('<script>window.customHtmlBlock=true;</script>', false);
    }

    public function test_feature_grid_static_items_remain_backward_compatible(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'feature_grid',
                    'data' => [
                        'section_title' => 'Static Features',
                        'items' => [
                            [
                                'title' => 'Manual Feature',
                                'description' => 'Manual feature description.',
                                'button_label' => 'Manual Button',
                                'button_url' => '/manual',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Static Features')
            ->assertSee('Manual Feature')
            ->assertSee('Manual feature description.')
            ->assertSee('/manual', false);
    }

    public function test_stats_section_block_renders_configured_numbers(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'stats_section',
                    'data' => [
                        'section_title' => 'بازدید از خانه‌های نوآورانه LSF',
                        'section_description' => 'با صدها مقاله، ویدیو، ابزار و موارد مفید دیگر.',
                        'inner_width_percent' => 65,
                        'items' => [
                            ['value' => '15', 'label' => 'سال سابقه'],
                            ['value' => '2,000', 'label' => 'مشتری', 'description' => 'همراهان فعال'],
                            ['value' => '1,870', 'label' => 'پروژه ساختمانی'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('block-stats-section', false)
            ->assertSee('--stats-inner-width: 65%', false)
            ->assertSee('data-stats-counter', false)
            ->assertSee('data-counter-target="2000"', false)
            ->assertSee('data-counter-formatted="2,000"', false)
            ->assertSee('بازدید از خانه‌های نوآورانه LSF')
            ->assertSee('15')
            ->assertSee('سال سابقه')
            ->assertSee('2,000')
            ->assertSee('مشتری')
            ->assertSee('همراهان فعال')
            ->assertSee('1,870')
            ->assertSee('پروژه ساختمانی');
    }

    public function test_dynamic_feature_grid_renders_latest_posts_with_limit_and_button_override(): void
    {
        $posts = collect(range(1, 6))->map(fn (int $index) => Post::factory()->published()->create([
            'title' => "Dynamic Post {$index}",
            'excerpt' => "Excerpt {$index}",
            'published_at' => now()->subMinutes(6 - $index),
        ]));
        $latestPost = $posts->last();

        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'feature_grid',
                    'data' => [
                        'section_title' => 'Dynamic Posts',
                        'items_mode' => 'dynamic',
                        'dynamic_source' => 'posts',
                        'dynamic_rows' => 1,
                        'dynamic_columns' => 10,
                        'dynamic_grid_width' => 1000,
                        'dynamic_item_width' => 220,
                        'dynamic_button_label' => 'Read More',
                        'dynamic_button_overrides' => [
                            [
                                'record_id' => $latestPost->id,
                                'button_label' => 'Read Exact Post',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Dynamic Posts')
            ->assertSee('Dynamic Post 6')
            ->assertSee('Dynamic Post 5')
            ->assertSee('Dynamic Post 4')
            ->assertSee('Dynamic Post 3')
            ->assertDontSee('Dynamic Post 2')
            ->assertDontSee('Dynamic Post 1')
            ->assertSee('Excerpt 6')
            ->assertSee('Read Exact Post')
            ->assertSee('Read More')
            ->assertSee(route('blog.show', $latestPost->slug), false)
            ->assertSee('--feature-grid-width: 1000px', false)
            ->assertSee('--feature-grid-item-width: 220px', false)
            ->assertSee('--feature-grid-columns: 4', false);
    }

    public function test_dynamic_feature_grid_renders_latest_projects(): void
    {
        $olderProject = Project::factory()->published()->create([
            'title' => 'Older Dynamic Project',
            'excerpt' => 'Older project excerpt.',
            'published_at' => now()->subDays(2),
        ]);
        $latestProject = Project::factory()->published()->create([
            'title' => 'Latest Dynamic Project',
            'excerpt' => 'Latest project excerpt.',
            'published_at' => now()->subDay(),
        ]);

        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'feature_grid',
                    'data' => [
                        'section_title' => 'Dynamic Projects',
                        'items_mode' => 'dynamic',
                        'dynamic_source' => 'projects',
                        'dynamic_rows' => 1,
                        'dynamic_columns' => 1,
                        'dynamic_grid_width' => 1180,
                        'dynamic_item_width' => 280,
                        'dynamic_button_label' => 'View Project',
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Dynamic Projects')
            ->assertSee('Latest Dynamic Project')
            ->assertSee('Latest project excerpt.')
            ->assertSee('View Project')
            ->assertSee($latestProject->resolveNavigationUrl(), false)
            ->assertDontSee('Older Dynamic Project')
            ->assertDontSee($olderProject->resolveNavigationUrl(), false);
    }

    public function test_hero_three_split_stats_template_renders(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'Home',
            'blocks' => [
                [
                    'type' => 'hero',
                    'data' => [
                        'template' => 'hero_3',
                        'hero_3_alignment' => 'left',
                        'eyebrow' => 'Smart industrial solutions',
                        'title' => 'Grow with smarter systems',
                        'subtitle' => 'A full-width split hero with image, actions, and stats.',
                        'primary_button_label' => 'View Services',
                        'primary_button_url' => '/projects',
                        'secondary_button_label' => 'Free Consultation',
                        'secondary_button_url' => '/contact',
                        'image' => 'https://example.com/industry.jpg',
                        'stats' => [
                            ['value' => '65+', 'label' => 'Active contracts', 'description' => 'Long-term partners', 'icon' => 'Handshake'],
                            ['value' => '120+', 'label' => 'Customers', 'description' => 'Across the country', 'icon' => 'Users'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('hero-template-3 hero-template-3--left', false)
            ->assertSee('Smart industrial solutions')
            ->assertSee('Grow with smarter systems')
            ->assertSee('Active contracts')
            ->assertSee('Free Consultation');
    }

    public function test_published_page_loads_and_draft_page_returns_404(): void
    {
        $published = Page::factory()->published()->create([
            'slug' => 'about',
            'title' => 'About Us',
        ]);

        $draft = Page::factory()->draft()->create([
            'slug' => 'draft-page',
        ]);

        $this->get(route('pages.show', $published->slug))
            ->assertOk()
            ->assertSee('About Us');

        $this->get(route('pages.show', $draft->slug))
            ->assertNotFound();
    }

    public function test_blog_index_and_post_visibility(): void
    {
        $category = Category::factory()->create();
        $published = Post::factory()->published()->for($category)->create([
            'slug' => 'published-post',
            'title' => 'Published Post',
        ]);
        $draft = Post::factory()->draft()->for($category)->create([
            'slug' => 'draft-post',
            'title' => 'Draft Post',
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Published Post')
            ->assertDontSee('Draft Post');

        $this->get(route('blog.show', $published->slug))
            ->assertOk()
            ->assertSee('Published Post');

        $this->get(route('blog.show', $draft->slug))
            ->assertNotFound();
    }

    public function test_projects_index_and_project_visibility(): void
    {
        $category = ProjectCategory::factory()->create();
        $published = Project::factory()->published()->for($category, 'category')->create([
            'slug' => 'published-project',
            'title' => 'Published Project',
        ]);
        $draft = Project::factory()->draft()->for($category, 'category')->create([
            'slug' => 'draft-project',
            'title' => 'Draft Project',
        ]);

        $this->get(route('projects.index'))
            ->assertOk()
            ->assertSee('Published Project')
            ->assertDontSee('Draft Project');

        $this->get(route('projects.show', $published->slug))
            ->assertOk()
            ->assertSee('Published Project');

        $this->get(route('projects.show', $draft->slug))
            ->assertNotFound();
    }

    public function test_project_category_archive_loads(): void
    {
        $category = ProjectCategory::factory()->create([
            'name' => 'Case Studies',
            'slug' => 'case-studies',
        ]);

        Project::factory()->published()->for($category, 'category')->create([
            'title' => 'Category Project',
        ]);

        $this->get(route('projects.category', $category->slug))
            ->assertOk()
            ->assertSee('Case Studies')
            ->assertSee('Category Project');
    }

    public function test_contact_page_loads(): void
    {
        Page::factory()->published()->create([
            'slug' => 'contact',
            'title' => 'Contact',
        ]);

        $this->get(route('contact.create'))
            ->assertOk()
            ->assertSee('Contact')
            ->assertSee('name="robots"', false);
    }

    private function setting(string $key, string $value, string $group, string $type): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'type' => $type,
            ],
        );
    }
}
