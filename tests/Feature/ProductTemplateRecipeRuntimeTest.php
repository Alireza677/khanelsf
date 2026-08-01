<?php

namespace Tests\Feature;

use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\Models\Product;
use App\Models\Template;
use App\Services\TemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTemplateRecipeRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_recipe_persists_only_a_draft_and_enters_runtime_after_explicit_publish(): void
    {
        $product = Product::factory()->published()->create();
        $template = app(TemplateRecipeInstantiator::class)->createDraft('product-industrial-v1', [
            'title' => 'Industrial product draft',
            'slug' => 'industrial-product-draft',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'status' => 'published',
        ]);

        $this->assertTrue($template->exists);
        $this->assertSame('draft', $template->status);
        $this->assertDatabaseHas('templates', [
            'id' => $template->getKey(),
            'type' => 'product_single',
            'status' => 'draft',
        ]);
        $this->assertNull(app(TemplateService::class)->findTemplateFor('product_single', $product));

        $template->update(['status' => 'published']);

        $this->assertTrue(
            $template->is(app(TemplateService::class)->findTemplateFor('product_single', $product)),
        );
    }

    public function test_template_instantiated_from_recipe_renders_in_product_runtime(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'Industrial Recipe Product',
            'content' => '<p>Industrial recipe overview</p>',
        ]);
        $product->specifications()->create([
            'group_name' => 'Technical',
            'label' => 'Thickness',
            'value' => '1.25',
            'unit' => 'mm',
        ]);
        $product->documents()->create([
            'title' => 'Industrial Datasheet',
            'external_url' => 'https://example.com/industrial.pdf',
            'mime_type' => 'application/pdf',
        ]);
        $related = Product::factory()->published()->create([
            'title' => 'Related Industrial Product',
        ]);
        $product->relatedProducts()->attach($related, ['sort_order' => 1]);
        $template = app(TemplateRecipeInstantiator::class)->createDraft('product-industrial-v1', [
            'slug' => 'industrial-runtime-template',
            'is_default' => true,
        ]);
        $template->update(['status' => 'published']);

        $response = $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Industrial Recipe Product')
            ->assertSee('Industrial recipe overview', false)
            ->assertSee('Thickness')
            ->assertSee('Industrial Datasheet')
            ->assertSee('Related Industrial Product')
            ->assertSee('دریافت مشاوره');

        $this->assertStringNotContainsString(
            '<article class="project-detail product-detail">',
            $response->getContent(),
        );
    }

    public function test_recipe_instantiation_never_creates_a_product_record(): void
    {
        $before = Product::query()->count();

        app(TemplateRecipeInstantiator::class)->createDraft('product-industrial-v1', [
            'slug' => 'blueprint-only-product-template',
        ]);

        $this->assertSame($before, Product::query()->count());
        $this->assertSame(1, Template::query()->where('status', 'draft')->count());
    }
}
