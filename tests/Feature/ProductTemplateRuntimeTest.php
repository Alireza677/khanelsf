<?php

namespace Tests\Feature;

use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Template;
use App\Models\User;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTemplateRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_template_resolution_uses_specific_then_category_then_default_priority(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create([
            'product_category_id' => $category->getKey(),
        ]);
        $default = $this->template('Default product template');
        $categoryTemplate = $this->template('Category product template', [
            'is_default' => false,
            'conditions' => ['type' => 'category', 'category_id' => $category->getKey()],
        ]);
        $specific = $this->template('Specific product template', [
            'is_default' => false,
            'conditions' => ['type' => 'specific_item', 'item_id' => $product->getKey()],
        ]);
        $templates = app(TemplateService::class);

        $this->assertTrue($specific->is($templates->findTemplateFor('product_single', $product)));

        $specific->delete();
        $this->assertTrue($categoryTemplate->is($templates->findTemplateFor('product_single', $product)));

        $categoryTemplate->delete();
        $this->assertTrue($default->is($templates->findTemplateFor('product_single', $product)));
    }

    public function test_product_single_renders_registered_blocks_through_the_standard_runtime(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'Standard Runtime Product',
            'content' => '<p>Standard runtime overview</p>',
        ]);
        $product->specifications()->create([
            'label' => 'Runtime specification',
            'value' => '42',
        ]);
        $product->documents()->create([
            'title' => 'Runtime document',
            'external_url' => 'https://example.com/runtime.pdf',
        ]);
        $related = Product::factory()->published()->create([
            'title' => 'Runtime related product',
        ]);
        $product->relatedProducts()->attach($related, ['sort_order' => 1]);
        $this->template('Complete product runtime', [
            'blocks' => $this->recommendedBlocks(),
        ]);

        $response = $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Standard Runtime Product')
            ->assertSee('Standard runtime overview', false)
            ->assertSee('Runtime specification')
            ->assertSee('Runtime document')
            ->assertSee('Runtime related product')
            ->assertSee('Product Runtime CTA');

        $this->assertStringNotContainsString(
            '<article class="project-detail product-detail">',
            $response->getContent(),
        );
    }

    public function test_category_template_is_used_when_no_specific_template_matches(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create([
            'product_category_id' => $category->getKey(),
            'content' => '<p>Category fallback body</p>',
        ]);
        $this->template('Default marker', [
            'blocks' => $this->overviewBlock('Default marker'),
        ]);
        $this->template('Category marker', [
            'is_default' => false,
            'conditions' => ['type' => 'category', 'category_id' => $category->getKey()],
            'blocks' => $this->overviewBlock('Category marker'),
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Category marker')
            ->assertDontSee('Default marker');
    }

    public function test_default_product_template_is_used_as_the_final_template_fallback(): void
    {
        $product = Product::factory()->published()->create([
            'content' => '<p>Default fallback body</p>',
        ]);
        $this->template('Default fallback', [
            'blocks' => $this->overviewBlock('Default product runtime'),
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Default product runtime')
            ->assertSee('Default fallback body', false);
    }

    public function test_recommended_default_product_template_structure_can_be_stored_in_order(): void
    {
        $template = $this->template('Creatable default product template', [
            'status' => 'draft',
            'blocks' => $this->recommendedBlocks(),
        ]);

        $this->assertSame([
            'product_header',
            'product_overview',
            'product_specifications',
            'product_gallery',
            'product_documents',
            'product_related',
            'cta',
        ], collect($template->fresh()->blocks)->pluck('type')->all());
        $this->assertTrue($template->is_default);
        $this->assertSame('product_single', $template->type);
    }

    public function test_product_preview_uses_the_same_runtime_and_forces_noindex(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->published()->create([
            'title' => 'Noindex Preview Product',
        ]);
        $this->template('Preview product template', [
            'blocks' => [
                ['type' => 'product_header', 'data' => []],
            ],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.preview.products.show', $product))
            ->assertOk()
            ->assertSee('Noindex Preview Product');

        $this->assertStringContainsString(
            '<meta name="robots" content="noindex, nofollow">',
            $response->getContent(),
        );
        $this->assertStringNotContainsString(
            '<article class="project-detail product-detail">',
            $response->getContent(),
        );
    }

    public function test_legacy_product_without_foundation_data_renders_in_template_runtime(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'Legacy Runtime Product',
            'content' => '<p>Legacy runtime content</p>',
        ]);
        $this->template('Legacy compatible template', [
            'blocks' => $this->recommendedBlocks(),
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Legacy Runtime Product')
            ->assertSee('Legacy runtime content', false)
            ->assertDontSee('Product Specifications has no specifications');
    }

    public function test_legacy_blade_remains_the_fallback_when_no_template_matches(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'Legacy Blade Fallback Product',
        ]);

        $response = $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Legacy Blade Fallback Product');

        $this->assertStringContainsString(
            '<article class="project-detail product-detail">',
            $response->getContent(),
        );
    }

    private function template(string $title, array $overrides = []): Template
    {
        return Template::query()->create([
            'title' => $title,
            'slug' => str($title.' '.uniqid())->slug()->toString(),
            'type' => 'product_single',
            'status' => 'published',
            'priority' => 0,
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => $this->overviewBlock($title),
            ...$overrides,
        ]);
    }

    private function overviewBlock(string $title): array
    {
        return [[
            'type' => 'product_overview',
            'data' => [
                'content' => ['title' => $title],
            ],
        ]];
    }

    private function recommendedBlocks(): array
    {
        return [
            ['type' => 'product_header', 'data' => []],
            ['type' => 'product_overview', 'data' => []],
            ['type' => 'product_specifications', 'data' => []],
            ['type' => 'product_gallery', 'data' => []],
            ['type' => 'product_documents', 'data' => []],
            ['type' => 'product_related', 'data' => []],
            [
                'type' => 'cta',
                'data' => app(CTADataNormalizer::class)->normalize([
                    'content' => ['title' => 'Product Runtime CTA'],
                ]),
            ],
        ];
    }
}
