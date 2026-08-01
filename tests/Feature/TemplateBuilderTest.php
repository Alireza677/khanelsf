<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use App\Models\Template;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_can_be_created(): void
    {
        $template = Template::query()->create([
            'title' => 'Shop Header',
            'slug' => 'shop-header',
            'type' => 'shop_index',
            'status' => 'published',
            'is_default' => true,
            'blocks' => [
                ['type' => 'custom_html', 'data' => ['code' => '<div>Template block</div>']],
            ],
        ]);

        $this->assertDatabaseHas('templates', [
            'id' => $template->id,
            'slug' => 'shop-header',
            'type' => 'shop_index',
        ]);
    }

    public function test_shop_index_template_fully_replaces_default_shop_layout(): void
    {
        Product::factory()->published()->create(['title' => 'Default Grid Product']);
        $this->publishedTemplate('shop_index', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Shop Override Only</div>']],
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Shop Override Only')
            ->assertDontSee('Default Grid Product');
    }

    public function test_product_single_template_fully_replaces_default_product_layout(): void
    {
        $product = Product::factory()->published()->create(['title' => 'Template Product']);
        $this->publishedTemplate('product_single', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Product Override Only</div>']],
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Product Override Only')
            ->assertDontSee('Availability');
    }

    public function test_project_single_template_fully_replaces_default_project_layout(): void
    {
        $project = Project::factory()->published()->create(['title' => 'Template Project']);
        $this->publishedTemplate('project_single', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Project Override Only</div>']],
        ]);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee('Project Override Only')
            ->assertDontSee('Project Details');
    }

    public function test_post_single_template_fully_replaces_default_post_layout(): void
    {
        $post = Post::factory()->published()->create(['title' => 'Template Post']);
        $this->publishedTemplate('post_single', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Post Override Only</div>']],
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee('Post Override Only')
            ->assertDontSee('Related Posts');
    }

    public function test_gallery_single_template_fully_replaces_default_gallery_layout(): void
    {
        $gallery = Gallery::factory()->create(['title' => 'Template Gallery']);
        $this->publishedTemplate('gallery_single', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Gallery Override Only</div>']],
        ]);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('Gallery Override Only')
            ->assertDontSee('Images');
    }

    public function test_no_template_fallback_still_uses_original_blade_view(): void
    {
        Product::factory()->published()->create(['title' => 'Fallback Product']);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('فروشگاه')
            ->assertSee('Fallback Product')
            ->assertDontSee('Template Banner');
    }

    public function test_draft_template_is_ignored(): void
    {
        Product::factory()->published()->create(['title' => 'Draft Ignored Product']);

        Template::query()->create([
            'title' => 'Draft Shop Template',
            'slug' => 'draft-shop-template',
            'type' => 'shop_index',
            'status' => 'draft',
            'is_default' => true,
            'blocks' => [
                ['type' => 'custom_html', 'data' => ['code' => '<div>Draft Template Banner</div>']],
            ],
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Draft Ignored Product')
            ->assertDontSee('Draft Template Banner');
    }

    public function test_dynamic_grid_block_renders_archive_items(): void
    {
        Product::factory()->published()->create(['title' => 'Dynamic Grid Product']);
        $this->publishedTemplate('shop_index', [
            ['type' => 'template_archive_header', 'data' => ['title' => 'Dynamic Shop Archive']],
            ['type' => 'template_content_grid', 'data' => []],
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Dynamic Shop Archive')
            ->assertSee('Dynamic Grid Product');
    }

    public function test_complete_shop_template_renders_and_filters_products(): void
    {
        $pipes = ProductCategory::factory()->create(['name' => 'Pipes', 'slug' => 'pipes']);
        $panels = ProductCategory::factory()->create(['name' => 'Panels', 'slug' => 'panels']);
        Product::factory()->published()->create([
            'product_category_id' => $pipes->id,
            'title' => 'Steel Pipe',
        ]);
        Product::factory()->published()->create([
            'product_category_id' => $panels->id,
            'title' => 'Steel Panel',
        ]);

        $this->publishedTemplate('shop_index', [
            [
                'type' => 'template_shop_complete',
                'data' => [
                    'title' => 'Complete Shop',
                    'category_section_title' => 'Buy by category',
                    'products_title' => 'Product list',
                ],
            ],
        ]);

        $this->get(route('shop.index', ['q' => 'Steel', 'category' => 'pipes']))
            ->assertOk()
            ->assertSee('Complete Shop')
            ->assertSee('Buy by category')
            ->assertSee('Pipes')
            ->assertSee('Steel Pipe')
            ->assertDontSee('Steel Panel');
    }

    public function test_dynamic_single_content_block_renders_current_item(): void
    {
        $post = Post::factory()->published()->create([
            'title' => 'Dynamic Single Post',
            'content' => '<p>Dynamic body content.</p>',
        ]);
        $this->publishedTemplate('post_single', [
            ['type' => 'template_single_header', 'data' => []],
            ['type' => 'template_single_content', 'data' => []],
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertSee('Dynamic Single Post')
            ->assertSee('Dynamic body content.', false);
    }

    public function test_add_to_cart_block_only_works_for_product_context(): void
    {
        $product = Product::factory()->published()->create(['title' => 'Cart Template Product']);
        $this->publishedTemplate('product_single', [
            ['type' => 'template_add_to_cart', 'data' => ['button_label' => 'Buy now']],
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Buy now')
            ->assertSee(route('cart.add', $product), false);

        $post = Post::factory()->published()->create(['title' => 'Wrong Context Post']);
        Template::query()->where('type', 'product_single')->delete();
        $this->publishedTemplate('post_single', [
            ['type' => 'template_add_to_cart', 'data' => ['button_label' => 'Buy now']],
        ]);

        $this->get(route('blog.show', $post->slug))
            ->assertOk()
            ->assertDontSee('Buy now');
    }

    public function test_disabled_shop_does_not_render_template(): void
    {
        $this->setting('shop_enabled', '0', 'shop', 'boolean');
        $this->publishedTemplate('shop_index', [
            ['type' => 'custom_html', 'data' => ['code' => '<div>Disabled Shop Template Banner</div>']],
        ]);

        $this->get(route('shop.index'))
            ->assertNotFound()
            ->assertDontSee('Disabled Shop Template Banner');
    }

    public function test_specific_product_template_overrides_product_category_template(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create([
            'product_category_id' => $category->id,
            'title' => 'Specific Product Match',
        ]);

        $this->publishedTemplate('product_single', $this->htmlBlock('Category Product Template'), [
            'conditions' => ['type' => 'category', 'category_id' => $category->id],
            'priority' => 100,
        ]);
        $this->publishedTemplate('product_single', $this->htmlBlock('Specific Product Template'), [
            'conditions' => ['type' => 'specific_item', 'item_id' => $product->id],
            'priority' => 1,
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Specific Product Template')
            ->assertDontSee('Category Product Template');
    }

    public function test_product_category_template_overrides_all_product_template(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create(['product_category_id' => $category->id]);

        $this->publishedTemplate('product_single', $this->htmlBlock('All Product Template'), [
            'conditions' => ['type' => 'all'],
            'priority' => 100,
        ]);
        $this->publishedTemplate('product_single', $this->htmlBlock('Category Product Template'), [
            'conditions' => ['type' => 'category', 'category_id' => $category->id],
            'priority' => 1,
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Category Product Template')
            ->assertDontSee('All Product Template');
    }

    public function test_higher_priority_wins_between_same_condition_level(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create(['product_category_id' => $category->id]);

        $this->publishedTemplate('product_single', $this->htmlBlock('Low Priority Category Template'), [
            'conditions' => ['type' => 'category', 'category_id' => $category->id],
            'priority' => 1,
        ]);
        $this->publishedTemplate('product_single', $this->htmlBlock('High Priority Category Template'), [
            'conditions' => ['type' => 'category', 'category_id' => $category->id],
            'priority' => 20,
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('High Priority Category Template')
            ->assertDontSee('Low Priority Category Template');
    }

    public function test_draft_conditional_template_is_ignored(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create(['product_category_id' => $category->id]);

        Template::query()->create([
            'title' => 'Draft Conditional',
            'slug' => 'draft-conditional',
            'type' => 'product_single',
            'status' => 'draft',
            'is_default' => false,
            'priority' => 100,
            'conditions' => ['type' => 'category', 'category_id' => $category->id],
            'blocks' => $this->htmlBlock('Draft Conditional Template'),
        ]);
        $this->publishedTemplate('product_single', $this->htmlBlock('Published All Template'), [
            'conditions' => ['type' => 'all'],
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Published All Template')
            ->assertDontSee('Draft Conditional Template');
    }

    public function test_project_category_template_applies_to_projects_in_that_category(): void
    {
        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->published()->create(['project_category_id' => $category->id]);

        $this->publishedTemplate('project_single', $this->htmlBlock('Project Category Template'), [
            'conditions' => ['type' => 'category', 'category_id' => $category->id],
        ]);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee('Project Category Template');
    }

    public function test_non_default_all_template_cannot_override_the_default_template(): void
    {
        $project = Project::factory()->published()->create();
        $default = $this->publishedTemplate('project_single', $this->htmlBlock('Default Project Template'), [
            'is_default' => true,
            'priority' => 1,
        ]);
        $this->publishedTemplate('project_single', $this->htmlBlock('Non-default All Template'), [
            'is_default' => false,
            'priority' => 100,
        ]);

        $matched = app(TemplateService::class)->findTemplateFor('project_single', $project);

        $this->assertTrue($default->is($matched));
    }

    public function test_specific_and_category_templates_do_not_need_default_flag_to_outrank_default(): void
    {
        $category = ProjectCategory::factory()->create();
        $project = Project::factory()->published()->create(['project_category_id' => $category->id]);
        $this->publishedTemplate('project_single', $this->htmlBlock('Default Project Template'));
        $categoryTemplate = $this->publishedTemplate('project_single', $this->htmlBlock('Category Project Template'), [
            'conditions' => ['type' => 'category', 'category_id' => $category->id],
            'is_default' => false,
        ]);

        $this->assertTrue(
            $categoryTemplate->is(app(TemplateService::class)->findTemplateFor('project_single', $project)),
        );

        $specificTemplate = $this->publishedTemplate('project_single', $this->htmlBlock('Specific Project Template'), [
            'conditions' => ['type' => 'specific_item', 'item_id' => $project->id],
            'is_default' => false,
            'priority' => 0,
        ]);

        $this->assertTrue(
            $specificTemplate->is(app(TemplateService::class)->findTemplateFor('project_single', $project)),
        );
    }

    public function test_specific_post_template_applies_only_to_that_post(): void
    {
        $target = Post::factory()->published()->create(['title' => 'Target Post']);
        $other = Post::factory()->published()->create(['title' => 'Other Post']);

        $this->publishedTemplate('post_single', $this->htmlBlock('All Posts Template'), [
            'conditions' => ['type' => 'all'],
        ]);
        $this->publishedTemplate('post_single', $this->htmlBlock('Specific Post Template'), [
            'conditions' => ['type' => 'specific_item', 'item_id' => $target->id],
        ]);

        $this->get(route('blog.show', $target->slug))
            ->assertOk()
            ->assertSee('Specific Post Template')
            ->assertDontSee('All Posts Template');

        $this->get(route('blog.show', $other->slug))
            ->assertOk()
            ->assertSee('All Posts Template')
            ->assertDontSee('Specific Post Template');
    }

    public function test_gallery_category_template_applies_to_galleries_in_that_category(): void
    {
        $category = GalleryCategory::factory()->create();
        $gallery = Gallery::factory()->create(['gallery_category_id' => $category->id]);

        $this->publishedTemplate('gallery_single', $this->htmlBlock('Gallery Category Template'), [
            'conditions' => ['type' => 'category', 'category_id' => $category->id],
        ]);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('Gallery Category Template');
    }

    public function test_specific_category_template_applies_to_category_archive(): void
    {
        $category = Category::factory()->create(['title' => 'Template News']);
        Post::factory()->published()->create(['category_id' => $category->id, 'title' => 'Category Archive Post']);

        $this->publishedTemplate('post_category', $this->htmlBlock('Specific Blog Category Template'), [
            'conditions' => ['type' => 'specific_item', 'item_id' => $category->id],
        ]);

        $this->get(route('blog.category', $category->slug))
            ->assertOk()
            ->assertSee('Specific Blog Category Template')
            ->assertDontSee('Category Archive Post');
    }

    public function test_no_matching_conditional_template_falls_back_to_default_or_blade(): void
    {
        $matchingCategory = ProductCategory::factory()->create();
        $otherCategory = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create([
            'product_category_id' => $otherCategory->id,
            'title' => 'Blade Fallback Product',
        ]);

        $this->publishedTemplate('product_single', $this->htmlBlock('Wrong Category Template'), [
            'conditions' => ['type' => 'category', 'category_id' => $matchingCategory->id],
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Blade Fallback Product')
            ->assertDontSee('Wrong Category Template');

        $this->publishedTemplate('product_single', $this->htmlBlock('Default All Product Template'), [
            'conditions' => ['type' => 'all'],
            'is_default' => true,
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Default All Product Template')
            ->assertDontSee('Wrong Category Template');
    }

    private function publishedTemplate(string $type, array $blocks, array $overrides = []): Template
    {
        return Template::query()->create([
            'title' => $type.' template',
            'slug' => str($type.' template '.uniqid())->slug()->toString(),
            'type' => $type,
            'status' => 'published',
            'is_default' => true,
            'priority' => 10,
            'blocks' => $blocks,
            'conditions' => ['type' => 'all'],
            ...$overrides,
        ]);
    }

    private function htmlBlock(string $content): array
    {
        return [
            ['type' => 'custom_html', 'data' => ['code' => "<div>{$content}</div>"]],
        ];
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
