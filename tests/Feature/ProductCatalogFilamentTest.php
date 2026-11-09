<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductAttributeResource\Pages\CreateProductAttribute;
use App\Filament\Resources\ProductAttributeResource\Pages\EditProductAttribute;
use App\Filament\Resources\ProductAttributeResource\RelationManagers\ValuesRelationManager;
use App\Filament\Resources\ProductBrandResource;
use App\Filament\Resources\ProductBrandResource\Pages\CreateProductBrand;
use App\Filament\Resources\ProductBrandResource\Pages\EditProductBrand;
use App\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductTagResource\Pages\CreateProductTag;
use App\Media\MediaConsumerRegistry;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductTag;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProductCatalogFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_create_brand_with_existing_media_logo_and_reopen_it(): void
    {
        Storage::fake('public');
        $media = auth()->user()->addMedia(UploadedFile::fake()->image('brand.jpg'))->toMediaCollection('media_library', 'public');
        Livewire::test(CreateProductBrand::class)->fillForm([
            'name' => 'برند نمونه', 'slug' => 'sample-brand', 'featured_media_id' => $media->id,
            'description' => 'برند مستقل', 'status' => 'active', 'sort_order' => 1,
        ])->call('create')->assertHasNoFormErrors();
        $brand = ProductBrand::sole();
        $this->assertSame($media->id, $brand->featuredImage()->id);
        Livewire::test(EditProductBrand::class, ['record' => $brand->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame($media->id, $brand->fresh()->featuredImage()->id);
        $this->assertSame(ProductBrandResource::class, app(MediaConsumerRegistry::class)->resource($brand));
    }

    public function test_admin_can_create_tag_and_attribute_with_category_recommendations(): void
    {
        $category = ProductCategory::factory()->create();
        Livewire::test(CreateProductTag::class)->fillForm(['name' => 'برچسب نمونه', 'slug' => 'sample-tag'])
            ->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateProductAttribute::class)->fillForm([
            'name' => 'جنس', 'slug' => 'material', 'categories' => [$category->id],
            'unit' => null, 'is_multiple' => false, 'is_filterable' => true, 'status' => 'active', 'sort_order' => 0,
        ])->call('create')->assertHasNoFormErrors();
        $attribute = ProductAttribute::sole();
        $this->assertSame(1, ProductTag::count());
        $this->assertTrue($attribute->categories->contains($category));
        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditProductAttribute::class])
            ->callTableAction('create', data: ['name' => 'فولاد', 'status' => 'active', 'sort_order' => 0])
            ->assertHasNoTableActionErrors();
        $this->assertSame('فولاد', $attribute->values()->sole()->name);
    }

    public function test_product_create_saves_brand_tags_and_multiple_attribute_values(): void
    {
        $brand = ProductBrand::create(['name' => 'Brand']);
        $tags = [ProductTag::create(['name' => 'First'])->id, ProductTag::create(['name' => 'Second'])->id];
        $attribute = ProductAttribute::create(['name' => 'Color', 'is_multiple' => true]);
        $values = [$attribute->values()->create(['name' => 'White'])->id, $attribute->values()->create(['name' => 'Black'])->id];
        Livewire::test(CreateProduct::class)->fillForm([
            'title' => 'Catalog Product', 'slug' => 'catalog-product', 'price' => '100.00',
            'product_brand_id' => $brand->id, 'tags' => $tags,
            'catalog_attributes' => [['attribute_id' => $attribute->id, 'value_ids' => $values]],
        ])->call('create')->assertHasNoFormErrors();
        $product = Product::sole();
        $this->assertSame($brand->id, $product->product_brand_id);
        $this->assertSame($tags, $product->tags()->orderBy('product_tags.id')->pluck('product_tags.id')->all());
        $this->assertEqualsCanonicalizing($values, $product->attributeAssignments()->pluck('product_attribute_value_id')->all());
    }

    public function test_editing_legacy_product_retains_content_specifications_price_history_and_catalog_data(): void
    {
        $brand = ProductBrand::create(['name' => 'Brand']);
        $tag = ProductTag::create(['name' => 'Tag']);
        $attribute = ProductAttribute::create(['name' => 'Material']);
        $value = $attribute->values()->create(['name' => 'Steel']);
        $category = ProductCategory::factory()->create();
        $other = ProductCategory::factory()->create();
        $product = Product::factory()->create(['content' => '<p>Legacy content</p>', 'product_brand_id' => $brand->id, 'product_category_id' => $category->id]);
        $spec = $product->specifications()->create(['key' => 'legacy', 'label' => 'قدیمی', 'value' => 'unchanged', 'sort_order' => 1]);
        $product->documents()->create(['title' => 'Document', 'external_url' => 'https://example.com/file.pdf', 'sort_order' => 1]);
        $product->tags()->sync([$tag->id]);
        app(ProductCatalogService::class)->syncAttributes($product, [['attribute_id' => $attribute->id, 'value_ids' => [$value->id]]]);
        $old = $product->getAttributes();
        $historyCount = $product->priceHistories()->count();
        $value->update(['status' => 'inactive']);
        $attribute->update(['status' => 'inactive']);
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->fillForm(['product_category_id' => $other->id])->call('save')->assertHasNoFormErrors();
        $product->refresh();
        foreach (['slug', 'content', 'price', 'sale_price', 'price_verified_at', 'price_changed_at', 'product_brand_id'] as $field) {
            $this->assertSame($old[$field] ?? null, $product->getAttributes()[$field] ?? null);
        }
        $this->assertSame($historyCount, $product->priceHistories()->count());
        $this->assertSame('unchanged', $spec->fresh()->value);
        $this->assertSame(1, $product->documents()->count());
        $this->assertSame(1, $product->attributeAssignments()->count());
        $this->assertSame([$tag->id], $product->tags()->pluck('product_tags.id')->all());
    }

    public function test_invalid_attribute_selection_rolls_back_the_whole_product_create(): void
    {
        $attribute = ProductAttribute::create(['name' => 'Material']);
        $foreign = ProductAttribute::create(['name' => 'Color'])->values()->create(['name' => 'White']);
        Livewire::test(CreateProduct::class)->fillForm([
            'title' => 'Invalid Product', 'slug' => 'invalid-product', 'price' => 100,
            'catalog_attributes' => [['attribute_id' => $attribute->id, 'value_ids' => [$foreign->id]]],
        ])->call('create')->assertHasFormErrors();
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_price_histories', 0);
    }

    public function test_category_editor_keeps_relationships_and_rejects_cycles(): void
    {
        $root = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
        $attribute = ProductAttribute::create(['name' => 'Material']);
        Livewire::test(EditProductCategory::class, ['record' => $child->id])
            ->fillForm(['attributes' => [$attribute->id]])->call('save')->assertHasNoFormErrors();
        $this->assertTrue($child->fresh()->attributes->contains($attribute));
        Livewire::test(EditProductCategory::class, ['record' => $root->id])
            ->fillForm(['parent_id' => $child->id])->call('save')->assertHasFormErrors();
        $this->assertNull($root->fresh()->parent_id);
    }

    public function test_admin_can_edit_attribute_options_and_in_use_delete_is_not_offered(): void
    {
        $attribute = ProductAttribute::create(['name' => 'Material']);
        $value = $attribute->values()->create(['name' => 'Steel']);
        app(ProductCatalogService::class)->syncAttributes(Product::factory()->create(), [['attribute_id' => $attribute->id, 'value_ids' => [$value->id]]]);
        Livewire::test(EditProductAttribute::class, ['record' => $attribute->id])->fillForm(['is_filterable' => true])
            ->call('save')->assertHasNoFormErrors();
        $this->assertTrue($attribute->fresh()->is_filterable);
        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $attribute->fresh(), 'pageClass' => EditProductAttribute::class])
            ->assertTableActionHidden('delete', $value)
            ->callTableAction('edit', $value, data: ['name' => 'Steel', 'status' => 'inactive', 'sort_order' => 0])
            ->assertHasNoTableActionErrors();
        $this->assertSame('inactive', $value->fresh()->status);
        $this->assertSame(1, $value->assignments()->count());
    }

    public function test_duplicate_value_reports_an_inline_name_error(): void
    {
        $attribute = ProductAttribute::create(['name' => 'Thickness', 'unit' => 'mm']);
        $attribute->values()->create(['name' => '۰٫۵']);
        Livewire::test(ValuesRelationManager::class, ['ownerRecord' => $attribute, 'pageClass' => EditProductAttribute::class])
            ->callTableAction('create', data: ['name' => '0.5', 'status' => 'active', 'sort_order' => 0])
            ->assertHasTableActionErrors(['name']);
        $this->assertSame(1, $attribute->values()->count());
    }

    public function test_invalid_attribute_change_rolls_back_product_and_tag_edits(): void
    {
        $attribute = ProductAttribute::create(['name' => 'Size']);
        $first = $attribute->values()->create(['name' => 'Small']);
        $second = $attribute->values()->create(['name' => 'Large']);
        $product = Product::factory()->create(['title' => 'Original title']);
        $tag = ProductTag::create(['name' => 'Original tag']);
        $product->tags()->sync([$tag->id]);
        app(ProductCatalogService::class)->syncAttributes($product, [['attribute_id' => $attribute->id, 'value_ids' => [$first->id]]]);
        Livewire::test(EditProduct::class, ['record' => $product->id])->fillForm([
            'title' => 'Unsaved title', 'tags' => [],
            'catalog_attributes' => [['attribute_id' => $attribute->id, 'value_ids' => [$first->id, $second->id]]],
        ])->call('save')->assertHasFormErrors();
        $this->assertSame('Original title', $product->fresh()->title);
        $this->assertSame([$tag->id], $product->tags()->pluck('product_tags.id')->all());
        $this->assertSame([$first->id], $product->attributeAssignments()->pluck('product_attribute_value_id')->all());
    }
}
