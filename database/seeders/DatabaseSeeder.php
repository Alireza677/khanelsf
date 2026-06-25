<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(LsfProductSeeder::class);

        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'مدیر',
                'password' => 'password',
                'is_admin' => true,
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'site_name'],
            [
                'value' => 'Starter CMS',
                'group' => 'general',
                'type' => 'text',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'site_description'],
            [
                'value' => 'A reusable website starter for client projects.',
                'group' => 'general',
                'type' => 'textarea',
            ],
        );

        foreach ([
            ['site_logo', '', 'branding', 'image'],
            ['site_favicon', '', 'branding', 'image'],
            ['image_placeholder', '', 'general', 'image'],
            ['health_check_enabled', '1', 'general', 'boolean'],
            ['default_og_image', '', 'seo', 'image'],
            ['footer_text', 'A practical website starter for client projects.', 'footer', 'textarea'],
            ['social_instagram_url', '', 'social', 'text'],
            ['social_telegram_url', '', 'social', 'text'],
            ['social_whatsapp_url', '', 'social', 'text'],
            ['social_linkedin_url', '', 'social', 'text'],
            ['social_x_url', '', 'social', 'text'],
            ['robots_txt', '', 'seo', 'textarea'],
            ['sitemap_enabled', '1', 'seo', 'boolean'],
            ['projects_enabled', '1', 'projects', 'boolean'],
            ['projects_label', 'Projects', 'projects', 'text'],
            ['projects_index_title', 'Projects', 'projects', 'text'],
            ['projects_index_description', 'Selected work and case studies.', 'projects', 'textarea'],
            ['projects_per_page', '12', 'projects', 'number'],
            ['projects_seo_title', 'Projects', 'projects', 'text'],
            ['projects_seo_description', 'Selected work and case studies.', 'projects', 'textarea'],
            ['projects_og_image', '', 'projects', 'image'],
            ['galleries_enabled', '1', 'galleries', 'boolean'],
            ['galleries_label', 'Galleries', 'galleries', 'text'],
            ['galleries_index_title', 'Galleries', 'galleries', 'text'],
            ['galleries_index_description', 'Browse image and video galleries.', 'galleries', 'textarea'],
            ['galleries_per_page', '12', 'galleries', 'number'],
            ['galleries_seo_title', 'Galleries', 'galleries', 'text'],
            ['galleries_seo_description', 'Browse image and video galleries.', 'galleries', 'textarea'],
            ['shop_enabled', '1', 'shop', 'boolean'],
            ['shop_label', 'فروشگاه', 'shop', 'text'],
            ['shop_index_title', 'فروشگاه', 'shop', 'text'],
            ['shop_index_description', 'Browse simple products and starter catalog items.', 'shop', 'textarea'],
            ['shop_per_page', '12', 'shop', 'number'],
            ['shop_seo_title', 'فروشگاه', 'shop', 'text'],
            ['shop_seo_description', 'Browse available products.', 'shop', 'textarea'],
            ['shop_order_admin_email', '', 'shop', 'text'],
            ['shop_manual_payment_message', 'Payment is manual for now. We will contact you to confirm payment and fulfillment.', 'shop', 'textarea'],
            ['payment_gateway', 'manual', 'payment', 'select'],
            ['zarinpal_access_token', '', 'payment', 'password'],
            ['zarinpal_graphql_endpoint', 'https://next.zarinpal.com/api/v4/graphql/', 'payment', 'text'],
            ['zarinpal_callback_url', '', 'payment', 'text'],
            ['primary_color', '#2563eb', 'theme', 'color'],
            ['secondary_color', '#111827', 'theme', 'color'],
            ['accent_color', '#0f766e', 'theme', 'color'],
            ['text_color', '#1f2937', 'theme', 'color'],
            ['link_color', '#2563eb', 'theme', 'color'],
            ['background_color', '#f8fafc', 'theme', 'color'],
            ['font_family', 'system', 'theme', 'select'],
            ['custom_font_name', 'Client Custom Font', 'theme', 'text'],
            ['custom_font_file', '', 'theme', 'file'],
            ['button_radius', '6px', 'theme', 'text'],
            ['container_width', '1180px', 'theme', 'text'],
        ] as [$key, $value, $group, $type]) {
            Setting::query()->firstOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'group' => $group,
                    'type' => $type,
                ],
            );
        }

        Setting::query()->firstOrCreate(
            ['key' => 'site_title'],
            [
                'value' => 'Starter CMS',
                'group' => 'seo',
                'type' => 'text',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'default_meta_description'],
            [
                'value' => 'A practical website starter CMS for small business websites.',
                'group' => 'seo',
                'type' => 'textarea',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'blog_seo_title'],
            [
                'value' => 'Blog',
                'group' => 'seo',
                'type' => 'text',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'blog_seo_description'],
            [
                'value' => 'Latest articles and updates.',
                'group' => 'seo',
                'type' => 'textarea',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'robots_disallow'],
            [
                'value' => '',
                'group' => 'seo',
                'type' => 'text',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'contact_email'],
            [
                'value' => 'hello@example.com',
                'group' => 'contact',
                'type' => 'text',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'contact_phone'],
            [
                'value' => '+1 555 123 4567',
                'group' => 'contact',
                'type' => 'text',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'contact_address'],
            [
                'value' => '123 Main Street, Your City',
                'group' => 'contact',
                'type' => 'textarea',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'header_cta_label'],
            [
                'value' => 'Contact Us',
                'group' => 'header',
                'type' => 'text',
            ],
        );

        Setting::query()->firstOrCreate(
            ['key' => 'header_cta_url'],
            [
                'value' => '/contact',
                'group' => 'header',
                'type' => 'text',
            ],
        );

        $homePage = Page::query()->firstOrCreate(
            ['slug' => 'home'],
            [
                'title' => 'خانه',
                'content' => '<p>Welcome to your new website. This starter CMS gives you editable pages, blog posts, menus, settings, and contact messages.</p>',
                'template' => 'home',
                'status' => 'published',
                'published_at' => now(),
                'seo_title' => 'Starter CMS',
                'seo_description' => 'A clean, practical website starter CMS for small business websites.',
            ],
        );

        if (blank($homePage->blocks)) {
            $homePage->update([
                'blocks' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'subtitle' => 'Reusable website starter',
                            'title' => 'Build client websites faster',
                            'description' => 'A clean Laravel and Filament foundation for corporate pages, blog content, media, SEO, menus, and configurable site settings.',
                            'primary_button_label' => 'Contact Us',
                            'primary_button_url' => '/contact',
                            'secondary_button_label' => 'Read the Blog',
                            'secondary_button_url' => '/blog',
                            'image' => '',
                        ],
                    ],
                    [
                        'type' => 'feature_grid',
                        'data' => [
                            'section_title' => 'Starter features',
                            'section_description' => 'Use these blocks as a starting point, then replace the copy for each client project.',
                            'items' => [
                                [
                                    'icon' => 'CMS',
                                    'title' => 'Editable pages',
                                    'description' => 'Create public pages with rich content, SEO fields, publishing controls, and reusable blocks.',
                                    'image' => '',
                                ],
                                [
                                    'icon' => 'SEO',
                                    'title' => 'SEO-ready foundation',
                                    'description' => 'Canonical tags, Open Graph metadata, robots controls, sitemap, and page-level SEO are included.',
                                    'image' => '',
                                ],
                                [
                                    'icon' => 'Media',
                                    'title' => 'Media workflow',
                                    'description' => 'A simple Media Library powers featured images and content editing workflows.',
                                    'image' => '',
                                ],
                            ],
                        ],
                    ],
                    [
                        'type' => 'cta',
                        'data' => [
                            'title' => 'Ready to customize this starter?',
                            'description' => 'Update the blocks, menus, settings, and content from the admin panel without editing Blade files.',
                            'button_label' => 'Open Contact Page',
                            'button_url' => '/contact',
                        ],
                    ],
                ],
            ]);
        }

        Page::query()->firstOrCreate(
            ['slug' => 'about'],
            [
                'title' => 'About',
                'content' => '<p>Use this page to introduce the company, explain what you do, and help visitors understand why they should work with you.</p>',
                'template' => 'default',
                'status' => 'published',
                'published_at' => now(),
                'seo_title' => 'About',
                'seo_description' => 'Learn more about our company and what we do.',
            ],
        );

        Page::query()->firstOrCreate(
            ['slug' => 'contact'],
            [
                'title' => 'Contact',
                'content' => '<p>Have a question or project in mind? Send a message and we will get back to you soon.</p>',
                'template' => 'default',
                'status' => 'published',
                'published_at' => now(),
                'seo_title' => 'Contact',
                'seo_description' => 'Contact us with your questions or project details.',
            ],
        );

        $category = Category::query()->firstOrCreate(
            ['slug' => 'news'],
            [
                'title' => 'News',
                'description' => 'Company news, updates, and helpful articles.',
                'status' => 'published',
            ],
        );

        Post::query()->firstOrCreate(
            ['slug' => 'welcome-to-the-blog'],
            [
                'category_id' => $category->id,
                'title' => 'Welcome to the Blog',
                'excerpt' => 'A short sample post to confirm the blog is working.',
                'content' => '<p>This is a sample published post. Edit it from the admin panel or replace it with your first real article.</p>',
                'status' => 'published',
                'published_at' => now(),
                'seo_title' => 'Welcome to the Blog',
                'seo_description' => 'A short sample blog post for the starter CMS.',
            ],
        );

        $webCategory = ProjectCategory::query()->updateOrCreate(
            ['slug' => 'web-design'],
            [
                'name' => 'Web Design',
                'description' => 'Website and digital experience projects.',
                'status' => 'active',
                'sort_order' => 1,
                'seo_title' => 'Web Design Projects',
                'seo_description' => 'Selected web design and development projects.',
            ],
        );

        $consultingCategory = ProjectCategory::query()->updateOrCreate(
            ['slug' => 'consulting'],
            [
                'name' => 'Consulting',
                'description' => 'Strategy, process, and business improvement work.',
                'status' => 'active',
                'sort_order' => 2,
                'seo_title' => 'Consulting Projects',
                'seo_description' => 'Selected consulting and service improvement projects.',
            ],
        );

        foreach ([
            [
                'slug' => 'corporate-website-launch',
                'project_category_id' => $webCategory->id,
                'title' => 'Corporate Website Launch',
                'excerpt' => 'A clean corporate website foundation for a growing service business.',
                'content' => '<p>This sample project demonstrates how a client website case study can present goals, delivery scope, and results.</p>',
                'client_name' => 'Acme Services',
                'location' => 'New York',
                'project_date' => now()->subMonths(2)->toDateString(),
                'services' => [
                    ['name' => 'Website strategy'],
                    ['name' => 'CMS setup'],
                    ['name' => 'SEO foundation'],
                ],
                'attributes' => [
                    ['label' => 'Timeline', 'value' => '6 weeks'],
                    ['label' => 'Platform', 'value' => 'Laravel + Filament'],
                ],
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'service-process-improvement',
                'project_category_id' => $consultingCategory->id,
                'title' => 'Service Process Improvement',
                'excerpt' => 'A sample consulting project focused on improving customer request handling.',
                'content' => '<p>Use this format for clinics, agencies, and service businesses that need to explain the before-and-after of a project.</p>',
                'client_name' => 'Northside Clinic',
                'location' => 'Chicago',
                'project_date' => now()->subMonths(5)->toDateString(),
                'services' => [
                    ['name' => 'Workflow review'],
                    ['name' => 'Content planning'],
                    ['name' => 'Implementation support'],
                ],
                'attributes' => [
                    ['label' => 'Outcome', 'value' => 'Clearer intake workflow'],
                    ['label' => 'Team', 'value' => 'Operations and marketing'],
                ],
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'portfolio-showcase-refresh',
                'project_category_id' => $webCategory->id,
                'title' => 'Portfolio Showcase Refresh',
                'excerpt' => 'A visual project listing pattern for agencies and personal portfolios.',
                'content' => '<p>This sample can be adapted for interior design, architecture, creative work, and completed-service galleries.</p>',
                'client_name' => 'Studio Example',
                'location' => 'Remote',
                'project_date' => now()->subMonths(8)->toDateString(),
                'services' => [
                    ['name' => 'Portfolio structure'],
                    ['name' => 'Project templates'],
                ],
                'attributes' => [
                    ['label' => 'Project count', 'value' => '12 showcased items'],
                ],
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ] as $project) {
            Project::query()->updateOrCreate(
                ['slug' => $project['slug']],
                [
                    ...$project,
                    'status' => 'published',
                    'published_at' => now(),
                    'seo_title' => $project['title'],
                    'seo_description' => $project['excerpt'],
                    'robots_index' => true,
                    'robots_follow' => true,
                ],
            );
        }

        $photoGalleryCategory = GalleryCategory::query()->updateOrCreate(
            ['slug' => 'project-photos'],
            [
                'name' => 'Project Photos',
                'description' => 'Photo galleries connected to client work and portfolio media.',
                'status' => 'active',
                'sort_order' => 1,
                'seo_title' => 'Project Photo Galleries',
                'seo_description' => 'Browse selected project photo galleries.',
            ],
        );

        $videoGalleryCategory = GalleryCategory::query()->updateOrCreate(
            ['slug' => 'videos'],
            [
                'name' => 'Videos',
                'description' => 'Video galleries and embedded media links.',
                'status' => 'active',
                'sort_order' => 2,
                'seo_title' => 'Video Galleries',
                'seo_description' => 'Browse selected video galleries.',
            ],
        );

        foreach ([
            [
                'slug' => 'corporate-website-media',
                'gallery_category_id' => $photoGalleryCategory->id,
                'project_slug' => 'corporate-website-launch',
                'title' => 'Corporate Website Media',
                'excerpt' => 'A sample image gallery connected to a project case study.',
                'content' => '<p>Use this gallery for screenshots, event photos, before/after media, or project progress images.</p>',
                'type' => 'image',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'service-process-video',
                'gallery_category_id' => $videoGalleryCategory->id,
                'project_slug' => 'service-process-improvement',
                'title' => 'Service Process Video',
                'excerpt' => 'A sample video gallery that uses an external video URL.',
                'content' => '<p>This gallery demonstrates the lightweight video URL workflow without video transcoding.</p>',
                'type' => 'video',
                'video_url' => 'https://example.com/video',
                'is_featured' => true,
                'sort_order' => 2,
            ],
        ] as $gallery) {
            $project = Project::query()->where('slug', $gallery['project_slug'])->first();

            Gallery::query()->updateOrCreate(
                ['slug' => $gallery['slug']],
                [
                    'gallery_category_id' => $gallery['gallery_category_id'],
                    'project_id' => $project?->id,
                    'title' => $gallery['title'],
                    'excerpt' => $gallery['excerpt'],
                    'content' => $gallery['content'],
                    'type' => $gallery['type'],
                    'video_url' => $gallery['video_url'] ?? null,
                    'status' => 'published',
                    'published_at' => now(),
                    'is_featured' => $gallery['is_featured'],
                    'sort_order' => $gallery['sort_order'],
                    'seo_title' => $gallery['title'],
                    'seo_description' => $gallery['excerpt'],
                    'robots_index' => true,
                    'robots_follow' => true,
                ],
            );
        }

        $digitalCategory = ProductCategory::query()->updateOrCreate(
            ['slug' => 'digital-products'],
            [
                'name' => 'محصولات دیجیتال',
                'description' => 'Simple downloadable or service-style products.',
                'status' => 'active',
                'sort_order' => 1,
                'seo_title' => 'محصولات دیجیتال',
                'seo_description' => 'Browse digital products and starter catalog items.',
            ],
        );

        $serviceCategory = ProductCategory::query()->updateOrCreate(
            ['slug' => 'service-packages'],
            [
                'name' => 'Service Packages',
                'description' => 'Lightweight service packages for starter websites.',
                'status' => 'active',
                'sort_order' => 2,
                'seo_title' => 'Service Packages',
                'seo_description' => 'Browse simple service packages.',
            ],
        );

        foreach ([
            [
                'slug' => 'starter-website-audit',
                'product_category_id' => $serviceCategory->id,
                'title' => 'Starter Website Audit',
                'excerpt' => 'A simple sample service product for reviewing a client website.',
                'content' => '<p>Use this sample product for service businesses that sell lightweight packages without inventory tracking.</p>',
                'price' => 199,
                'sale_price' => null,
                'sku' => 'SVC-AUDIT',
                'is_featured' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'content-planning-kit',
                'product_category_id' => $digitalCategory->id,
                'title' => 'Content Planning Kit',
                'excerpt' => 'A digital product example for starter shops.',
                'content' => '<p>This sample can represent a downloadable template, guide, or other simple catalog item.</p>',
                'price' => 49,
                'sale_price' => 39,
                'sku' => 'DIG-KIT',
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'slug' => 'seo-setup-package',
                'product_category_id' => $serviceCategory->id,
                'title' => 'SEO Setup Package',
                'excerpt' => 'A sample service package with a fixed price.',
                'content' => '<p>This product demonstrates a simple fixed-price service checkout flow.</p>',
                'price' => 299,
                'sale_price' => null,
                'sku' => 'SVC-SEO',
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ] as $product) {
            Product::query()->updateOrCreate(
                ['slug' => $product['slug']],
                [
                    ...$product,
                    'status' => 'published',
                    'published_at' => now(),
                    'has_stock' => true,
                    'stock_status' => 'in_stock',
                    'seo_title' => $product['title'],
                    'seo_description' => $product['excerpt'],
                    'robots_index' => true,
                    'robots_follow' => true,
                ],
            );
        }

        $menu = Menu::query()->firstOrCreate(
            ['slug' => 'main-menu'],
            [
                'title' => 'Main Menu',
                'location' => 'main',
                'status' => 'active',
            ],
        );

        $items = [
            ['title' => 'خانه', 'url' => '/', 'sort_order' => 1],
            ['title' => 'About', 'url' => '/about', 'sort_order' => 2],
            ['title' => 'Projects', 'url' => '/projects', 'sort_order' => 3],
            ['title' => 'Galleries', 'url' => '/galleries', 'sort_order' => 4],
            ['title' => 'فروشگاه', 'url' => '/shop', 'sort_order' => 5],
            ['title' => 'Blog', 'url' => '/blog', 'sort_order' => 6],
            ['title' => 'Contact', 'url' => '/contact', 'sort_order' => 7],
        ];

        foreach ($items as $item) {
            MenuItem::query()->firstOrCreate(
                [
                    'menu_id' => $menu->id,
                    'title' => $item['title'],
                ],
                [
                    'parent_id' => null,
                    'url' => $item['url'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'status' => 'active',
                ],
            );
        }

        $footerMenu = Menu::query()->firstOrCreate(
            ['slug' => 'footer-menu'],
            [
                'title' => 'Footer Menu',
                'location' => 'footer',
                'status' => 'active',
            ],
        );

        foreach ([
            ['title' => 'About', 'url' => '/about', 'sort_order' => 1],
            ['title' => 'Projects', 'url' => '/projects', 'sort_order' => 2],
            ['title' => 'Galleries', 'url' => '/galleries', 'sort_order' => 3],
            ['title' => 'فروشگاه', 'url' => '/shop', 'sort_order' => 4],
            ['title' => 'Blog', 'url' => '/blog', 'sort_order' => 5],
            ['title' => 'Contact', 'url' => '/contact', 'sort_order' => 6],
        ] as $item) {
            MenuItem::query()->firstOrCreate(
                [
                    'menu_id' => $footerMenu->id,
                    'title' => $item['title'],
                ],
                [
                    'parent_id' => null,
                    'url' => $item['url'],
                    'target' => '_self',
                    'sort_order' => $item['sort_order'],
                    'status' => 'active',
                ],
            );
        }

        foreach ([
            ['shop-index-template', 'Shop Index Template', 'shop_index', 'Shop', 'Browse simple products and starter catalog items.'],
            ['projects-index-template', 'Projects Index Template', 'projects_index', 'Projects', 'Selected work and case studies.'],
            ['blog-index-template', 'Blog Index Template', 'blog_index', 'Blog', 'Latest articles and updates.'],
            ['galleries-index-template', 'Galleries Index Template', 'galleries_index', 'Galleries', 'Browse image and video galleries.'],
        ] as [$slug, $title, $type, $heading, $description]) {
            $template = Template::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'type' => $type,
                    'status' => 'published',
                    'is_default' => true,
                    'priority' => 0,
                ],
            );

            $blocks = collect($template->blocks);

            if ($blocks->where('type', 'template_content_grid')->isEmpty()) {
                $template->update([
                    'blocks' => [
                        [
                            'type' => 'template_archive_header',
                            'data' => [
                                'eyebrow' => 'Dynamic template',
                                'title' => $heading,
                                'description' => $description,
                            ],
                        ],
                        [
                            'type' => 'template_content_grid',
                            'data' => [
                                'empty_message' => 'No items have been published yet.',
                            ],
                        ],
                    ],
                ]);
            }
        }

        Template::query()->firstOrCreate(
            ['slug' => 'complete-shop-page-template'],
            [
                'title' => 'Complete Shop Page Template',
                'type' => 'shop_index',
                'status' => 'draft',
                'is_default' => false,
                'priority' => 0,
                'blocks' => [
                    [
                        'type' => 'template_shop_complete',
                        'data' => [
                            'eyebrow' => 'فروشگاه',
                            'title' => 'محصولات فروشگاه',
                            'description' => 'Search products, browse categories, and narrow the catalog with filters.',
                            'background_image' => '',
                            'overlay_opacity' => 20,
                            'search_placeholder' => 'Search products',
                            'category_label' => 'Categories',
                            'category_section_title' => 'خرید بر اساس دسته‌بندی',
                            'all_categories_image' => '',
                            'products_title' => 'محصولات',
                            'empty_message' => 'No products matched your filters.',
                        ],
                    ],
                ],
                'conditions' => ['type' => 'all'],
            ],
        );
    }
}
