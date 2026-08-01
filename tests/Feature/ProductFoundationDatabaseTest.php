<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductDocument;
use App\Models\ProductSpecification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFoundationDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_can_have_ordered_structured_specifications(): void
    {
        $product = Product::factory()->create();

        $product->specifications()->createMany([
            [
                'group_name' => 'Dimensions',
                'key' => 'width',
                'label' => 'Width',
                'value' => '120',
                'unit' => 'mm',
                'sort_order' => 2,
            ],
            [
                'group_name' => 'Dimensions',
                'key' => 'height',
                'label' => 'Height',
                'value' => '240',
                'unit' => 'mm',
                'sort_order' => 1,
            ],
        ]);

        $this->assertSame(
            ['height', 'width'],
            $product->specifications()->pluck('key')->all(),
        );
        $this->assertInstanceOf(
            ProductSpecification::class,
            $product->specifications()->firstOrFail(),
        );
        $this->assertTrue($product->is($product->specifications()->firstOrFail()->product));
    }

    public function test_product_document_relation_stores_optional_file_metadata(): void
    {
        $product = Product::factory()->create();

        $document = $product->documents()->create([
            'title' => 'Technical datasheet',
            'disk' => 'public',
            'file_path' => 'product-documents/datasheet.pdf',
            'original_name' => 'datasheet.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ]);

        $this->assertInstanceOf(ProductDocument::class, $document);
        $this->assertTrue($product->is($document->product));
        $this->assertDatabaseHas('product_documents', [
            'id' => $document->id,
            'product_id' => $product->id,
            'file_path' => 'product-documents/datasheet.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
        ]);
    }

    public function test_related_products_are_directional_and_have_an_inverse_relation(): void
    {
        $product = Product::factory()->create();
        $firstRelated = Product::factory()->create();
        $secondRelated = Product::factory()->create();

        $product->relatedProducts()->attach([
            $firstRelated->id => ['sort_order' => 2],
            $secondRelated->id => ['sort_order' => 1],
        ]);

        $this->assertSame(
            [$secondRelated->id, $firstRelated->id],
            $product->relatedProducts()->pluck('products.id')->all(),
        );
        $this->assertSame([$product->id], $firstRelated->relatedBy()->pluck('products.id')->all());
        $this->assertFalse($firstRelated->relatedProducts()->whereKey($product->getKey())->exists());
    }

    public function test_legacy_product_remains_valid_without_foundation_records(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'Legacy Product',
            'slug' => 'legacy-product',
            'content' => '<p>Existing product content.</p>',
            'price' => 125000,
            'sale_price' => null,
            'sku' => null,
        ]);

        $this->assertTrue($product->specifications->isEmpty());
        $this->assertTrue($product->documents->isEmpty());
        $this->assertTrue($product->relatedProducts->isEmpty());
        $this->assertTrue($product->relatedBy->isEmpty());
        $this->assertSame(125000.0, $product->currentPrice());

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Legacy Product')
            ->assertSee('Existing product content.');
    }
}
