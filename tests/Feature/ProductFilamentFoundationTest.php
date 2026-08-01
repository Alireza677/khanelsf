<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductFilamentFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_a_product_specification_from_filament(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProduct::class)
            ->fillForm([
                ...$this->baseProductData('Specification Product'),
                'specifications' => [[
                    'group_name' => 'Dimensions',
                    'key' => 'thickness',
                    'label' => 'Thickness',
                    'value' => '1.25',
                    'unit' => 'mm',
                    'sort_order' => 2,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'specification-product')->firstOrFail();

        $this->assertDatabaseHas('product_specifications', [
            'product_id' => $product->id,
            'group_name' => 'Dimensions',
            'key' => 'thickness',
            'label' => 'Thickness',
            'value' => '1.25',
            'unit' => 'mm',
            'sort_order' => 2,
        ]);
    }

    public function test_admin_can_save_a_product_document_from_filament(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateProduct::class)
            ->fillForm([
                ...$this->baseProductData('Document Product'),
                'documents' => [[
                    'title' => 'Technical datasheet',
                    'external_url' => 'https://example.com/datasheet.pdf',
                    'mime_type' => 'application/pdf',
                    'disk' => 'public',
                    'sort_order' => 1,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'document-product')->firstOrFail();

        $this->assertDatabaseHas('product_documents', [
            'product_id' => $product->id,
            'title' => 'Technical datasheet',
            'external_url' => 'https://example.com/datasheet.pdf',
            'mime_type' => 'application/pdf',
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_assign_an_ordered_related_product(): void
    {
        $this->actingAs(User::factory()->create());
        $relatedProduct = Product::factory()->published()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                ...$this->baseProductData('Related Product Owner'),
                'related_products' => [[
                    'product_id' => $relatedProduct->id,
                    'sort_order' => 4,
                ]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('slug', 'related-product-owner')->firstOrFail();

        $this->assertDatabaseHas('product_related_product', [
            'product_id' => $product->id,
            'related_product_id' => $relatedProduct->id,
            'sort_order' => 4,
        ]);
    }

    public function test_sale_price_and_sku_validation_are_enforced(): void
    {
        $this->actingAs(User::factory()->create());
        Product::factory()->create(['sku' => 'UNIQUE-SKU']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                ...$this->baseProductData('Invalid Product'),
                'price' => 100000,
                'sale_price' => 100000,
                'sku' => 'UNIQUE-SKU',
            ])
            ->call('create')
            ->assertHasFormErrors([
                'sale_price',
                'sku',
            ]);

        $this->assertDatabaseMissing('products', ['slug' => 'invalid-product']);
    }

    public function test_legacy_product_without_foundation_relations_can_still_be_saved(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create([
            'title' => 'Legacy Product',
            'slug' => 'legacy-product',
            'content' => '<p>Legacy content remains.</p>',
            'price' => 75000,
            'sku' => null,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $product->refresh();

        $this->assertSame('Legacy Product', $product->title);
        $this->assertSame('<p>Legacy content remains.</p>', $product->content);
        $this->assertSame(75000.0, $product->currentPrice());
        $this->assertTrue($product->specifications()->doesntExist());
        $this->assertTrue($product->documents()->doesntExist());
        $this->assertTrue($product->relatedProducts()->doesntExist());
    }

    private function baseProductData(string $title): array
    {
        return [
            'title' => $title,
            'slug' => str($title)->slug()->toString(),
            'price' => 100000,
            'sale_price' => null,
            'sku' => null,
            'has_stock' => true,
            'stock_status' => 'in_stock',
            'status' => 'draft',
            'sort_order' => 0,
        ];
    }
}
