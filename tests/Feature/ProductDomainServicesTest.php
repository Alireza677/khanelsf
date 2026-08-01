<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductAvailabilityService;
use App\Services\ProductPricingService;
use App\Services\ProductQueryService;
use App\Services\ProductTemplateContextBuilder;
use App\Support\SeoData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDomainServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_service_returns_only_published_products_and_supports_category_and_search(): void
    {
        $firstCategory = ProductCategory::factory()->create();
        $secondCategory = ProductCategory::factory()->create();
        $matchingProduct = Product::factory()->published()->create([
            'product_category_id' => $firstCategory->id,
            'title' => 'Steel Wall Panel',
        ]);
        $otherProduct = Product::factory()->published()->create([
            'product_category_id' => $secondCategory->id,
            'title' => 'Roof Insulation',
        ]);
        Product::factory()->draft()->create([
            'product_category_id' => $firstCategory->id,
            'title' => 'Steel Draft',
        ]);
        Product::factory()->create([
            'product_category_id' => $firstCategory->id,
            'title' => 'Steel Scheduled',
            'status' => 'published',
            'published_at' => now()->addDay(),
        ]);

        $service = app(ProductQueryService::class);

        $this->assertEqualsCanonicalizing(
            [$matchingProduct->id, $otherProduct->id],
            $service->publishedProducts()->pluck('products.id')->all(),
        );
        $this->assertSame(
            [$matchingProduct->id],
            $service->productsForCategory($firstCategory)->pluck('products.id')->all(),
        );
        $this->assertSame(
            [$matchingProduct->id],
            $service->search('Steel')->pluck('products.id')->all(),
        );
    }

    public function test_pricing_service_calculates_effective_price_and_uses_irt_currency(): void
    {
        $saleProduct = Product::factory()->make([
            'price' => 150000,
            'sale_price' => 125000,
        ]);
        $regularProduct = Product::factory()->make([
            'price' => 150000,
            'sale_price' => null,
        ]);
        $invalidSaleProduct = Product::factory()->make([
            'price' => 150000,
            'sale_price' => 175000,
        ]);

        $service = app(ProductPricingService::class);

        $this->assertSame(150000.0, $service->regularPrice($saleProduct));
        $this->assertSame(125000.0, $service->salePrice($saleProduct));
        $this->assertSame(125000.0, $service->effectivePrice($saleProduct));
        $this->assertNull($service->salePrice($regularProduct));
        $this->assertSame(150000.0, $service->effectivePrice($regularProduct));
        $this->assertSame(150000.0, $service->effectivePrice($invalidSaleProduct));
        $this->assertSame('IRT', $service->currency());
    }

    public function test_context_builder_generates_the_standard_product_context(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create([
            'product_category_id' => $category->id,
            'price' => 200000,
            'sale_price' => 180000,
        ]);
        $product->specifications()->create([
            'label' => 'Thickness',
            'value' => '1.25',
            'unit' => 'mm',
        ]);
        $product->documents()->create([
            'title' => 'Datasheet',
            'external_url' => 'https://example.com/datasheet.pdf',
        ]);
        $related = Product::factory()->published()->create();
        $product->relatedProducts()->attach($related, ['sort_order' => 1]);

        $context = app(ProductTemplateContextBuilder::class)->build($product);

        $this->assertSame([
            'product',
            'category',
            'effectivePrice',
            'currency',
            'availability',
            'specifications',
            'documents',
            'media',
            'relatedProducts',
            'seo',
        ], array_keys($context));
        $this->assertTrue($product->is($context['product']));
        $this->assertTrue($category->is($context['category']));
        $this->assertSame(180000.0, $context['effectivePrice']);
        $this->assertSame('IRT', $context['currency']);
        $this->assertTrue($context['availability']['purchasable']);
        $this->assertSame('Thickness', $context['specifications']->firstOrFail()->label);
        $this->assertSame('Datasheet', $context['documents']->firstOrFail()->title);
        $this->assertSame(['featured', 'gallery', 'documents'], array_keys($context['media']));
        $this->assertNull($context['media']['featured']);
        $this->assertTrue($context['media']['gallery']->isEmpty());
        $this->assertSame('Datasheet', $context['media']['documents']->firstOrFail()['title']);
        $this->assertSame([$related->id], $context['relatedProducts']->modelKeys());
        $this->assertInstanceOf(SeoData::class, $context['seo']);
    }

    public function test_query_service_loads_explicit_related_products_and_supports_legacy_category_fallbacks(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create([
            'product_category_id' => $category->id,
        ]);
        $explicitRelated = Product::factory()->published()->create();
        $draftExplicit = Product::factory()->draft()->create();
        $product->relatedProducts()->attach([
            $explicitRelated->id => ['sort_order' => 1],
            $draftExplicit->id => ['sort_order' => 2],
        ]);

        $related = app(ProductQueryService::class)->relatedProducts($product, 3);

        $this->assertSame([$explicitRelated->id], $related->modelKeys());
        $this->assertTrue($related->every->relationLoaded('category'));
        $this->assertTrue($related->every->relationLoaded('media'));

        $legacyCategory = ProductCategory::factory()->create();
        $legacyProduct = Product::factory()->published()->create([
            'product_category_id' => $legacyCategory->id,
        ]);
        $legacyRelated = Product::factory()->published()->create([
            'product_category_id' => $legacyCategory->id,
        ]);

        $this->assertSame(
            [$legacyRelated->id],
            app(ProductQueryService::class)->relatedProducts($legacyProduct, 3)->modelKeys(),
        );
    }

    public function test_legacy_product_without_foundation_data_builds_a_complete_context(): void
    {
        $product = Product::factory()->published()->create([
            'price' => 99000,
            'sale_price' => null,
            'has_stock' => true,
            'stock_status' => 'in_stock',
        ]);

        $context = app(ProductTemplateContextBuilder::class)->build($product);
        $availability = app(ProductAvailabilityService::class);

        $this->assertSame(99000.0, $context['effectivePrice']);
        $this->assertSame('IRT', $context['currency']);
        $this->assertTrue($availability->isPurchasable($product));
        $this->assertSame('in_stock', $availability->stockStatus($product));
        $this->assertTrue($context['specifications']->isEmpty());
        $this->assertTrue($context['documents']->isEmpty());
        $this->assertNull($context['media']['featured']);
        $this->assertTrue($context['media']['gallery']->isEmpty());
        $this->assertTrue($context['media']['documents']->isEmpty());
        $this->assertTrue($context['relatedProducts']->isEmpty());
        $this->assertInstanceOf(SeoData::class, $context['seo']);
    }
}
