<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductTemplateContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductBlocksFoundationTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCKS = [
        'product_header',
        'product_overview',
        'product_specifications',
        'product_gallery',
        'product_documents',
        'product_related',
    ];

    public function test_registry_resolves_all_product_blocks_with_the_standard_contract(): void
    {
        $registry = app(BlockRegistry::class);

        foreach (self::BLOCKS as $key) {
            $definition = $registry->find($key);

            $this->assertInstanceOf(BlockNormalizer::class, $definition);
            $this->assertSame($key, $definition->key());
            $this->assertSame(1, $definition->version());
            $this->assertSame('default', $definition->defaultTemplate());
            $this->assertSame("partials.blocks.{$key}", $registry->renderView($key));
            $this->assertContains('product_context', $definition->capabilities());
            $this->assertContains('dynamic_data', $definition->capabilities());
        }
    }

    public function test_normalizers_and_editor_hydration_keep_canonical_product_block_identity(): void
    {
        $registry = app(BlockRegistry::class);

        foreach (self::BLOCKS as $key) {
            /** @var BlockNormalizer $normalizer */
            $normalizer = $registry->find($key);
            $normalized = $normalizer->normalize([
                'schema_version' => 99,
                'template' => 'unknown',
                'content' => ['title' => '  عنوان  '],
                'settings' => ['unexpected' => true],
                'legacy' => 'discarded',
            ]);

            $this->assertSame(
                ['block_id', 'schema_version', 'template', 'content', 'settings'],
                array_keys($normalized),
            );
            $this->assertSame(1, $normalized['schema_version']);
            $this->assertSame('default', $normalized['template']);
            $this->assertSame($normalized, $normalizer->normalize($normalized));
        }

        $hydrated = app(BlockEditorHydrator::class)->hydrate(array_map(
            fn (string $key): array => ['type' => $key, 'data' => []],
            self::BLOCKS,
        ));

        foreach ($hydrated as $block) {
            $this->assertNotEmpty($block['data']['block_id']);
            $this->assertSame(1, $block['data']['schema_version']);
        }
    }

    public function test_product_blocks_consume_only_the_standard_context(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'سازه سبک',
            'slug' => 'light-steel',
        ]);
        $product = Product::factory()->published()->create([
            'product_category_id' => $category->id,
            'title' => 'پروفیل سازه‌ای',
            'content' => '<p>معرفی فنی محصول</p>',
            'price' => 150000,
            'sale_price' => 120000,
        ]);
        $product->specifications()->create([
            'group_name' => 'ابعاد',
            'key' => 'thickness',
            'label' => 'ضخامت',
            'value' => '1.25',
            'unit' => 'میلی‌متر',
        ]);
        $product->documents()->create([
            'title' => 'دیتاشیت',
            'external_url' => 'https://example.com/product.pdf',
        ]);
        $related = Product::factory()->published()->create([
            'title' => 'محصول مکمل',
        ]);
        $product->relatedProducts()->attach($related, ['sort_order' => 1]);

        $context = app(ProductTemplateContextBuilder::class)->build($product);
        $html = collect(self::BLOCKS)
            ->map(fn (string $key): string => view(
                "partials.blocks.{$key}",
                ['data' => [], 'context' => $context],
            )->render())
            ->implode("\n");

        $this->assertStringContainsString('پروفیل سازه‌ای', $html);
        $this->assertStringContainsString('معرفی فنی محصول', $html);
        $this->assertStringContainsString('ضخامت', $html);
        $this->assertStringContainsString('دیتاشیت', $html);
        $this->assertStringContainsString('محصول مکمل', $html);
        $this->assertStringContainsString('تومان', $html);
    }

    public function test_rendering_product_blocks_executes_no_additional_database_queries(): void
    {
        $product = Product::factory()->published()->create();
        $product->specifications()->create([
            'label' => 'Thickness',
            'value' => '1.25',
        ]);
        $product->documents()->create([
            'title' => 'Datasheet',
            'external_url' => 'https://example.com/datasheet.pdf',
        ]);
        $context = app(ProductTemplateContextBuilder::class)->build($product);

        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach (self::BLOCKS as $key) {
            view("partials.blocks.{$key}", [
                'data' => [],
                'context' => $context,
            ])->render();
        }

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_legacy_product_without_foundation_data_renders_safely(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'محصول قدیمی',
            'content' => '<p>محتوای قدیمی</p>',
        ]);
        $context = app(ProductTemplateContextBuilder::class)->build($product);

        foreach (self::BLOCKS as $key) {
            $html = view("partials.blocks.{$key}", [
                'data' => [],
                'context' => $context,
            ])->render();

            $this->assertIsString($html);
        }

        $this->assertTrue($context['specifications']->isEmpty());
        $this->assertTrue($context['documents']->isEmpty());
        $this->assertTrue($context['relatedProducts']->isEmpty());
    }
}
