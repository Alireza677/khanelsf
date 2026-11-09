<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeAssignment;
use App\Models\ProductAttributeValue;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductTag;
use App\Models\User;
use App\Services\ProductCatalogService;
use App\Services\ProductPriceFreshness;
use App\Services\SettingsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_and_tags_are_optional_shared_and_safely_detached(): void
    {
        $brand = ProductBrand::create(['name' => 'Acme']);
        $tag = ProductTag::create(['name' => 'Reusable']);
        $otherTag = ProductTag::create(['name' => 'Popular']);
        $first = Product::factory()->create(['product_brand_id' => $brand->id]);
        $second = Product::factory()->create(['product_brand_id' => $brand->id]);
        $first->tags()->sync([$tag->id, $otherTag->id, $tag->id]);
        $second->tags()->sync([$tag->id]);
        $this->assertTrue($first->brand->is($brand));
        $this->assertSame(2, $tag->products()->count());
        $this->assertSame(2, $first->tags()->count());
        $brand->delete();
        $tag->delete();
        $this->assertNull($first->refresh()->product_brand_id);
        $this->assertSame([$otherTag->id], $first->tags()->pluck('product_tags.id')->all());
        $this->assertDatabaseHas('products', ['id' => $second->id]);
    }

    public function test_tag_names_are_unique_after_persian_and_whitespace_normalization(): void
    {
        ProductTag::create(['name' => 'کالا']);
        $this->expectException(ValidationException::class);
        ProductTag::create(['name' => '  كالا  ', 'slug' => 'different']);
    }

    public function test_shared_attribute_values_can_be_assigned_to_multiple_products(): void
    {
        $attribute = ProductAttribute::create(['name' => 'جنس', 'is_filterable' => true]);
        $steel = $attribute->values()->create(['name' => 'فولاد']);
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        foreach ([$first, $second] as $product) {
            $this->sync($product, $attribute, [$steel->id]);
        }
        $this->assertSame(2, $steel->assignments()->count());
        $this->assertTrue($first->attributeAssignments()->first()->value->is($steel));
        $this->assertTrue($attribute->is_filterable);
        $this->assertSame('choice', $attribute->data_type);
    }

    public function test_multiple_values_and_explicit_replacement_are_supported(): void
    {
        $attribute = ProductAttribute::create(['name' => 'رنگ', 'is_multiple' => true]);
        $white = $attribute->values()->create(['name' => 'سفید']);
        $black = $attribute->values()->create(['name' => 'مشکی']);
        $product = Product::factory()->create();
        $this->sync($product, $attribute, [$white->id, $black->id]);
        $this->assertSame(2, $product->attributeAssignments()->count());
        $this->sync($product, $attribute, [$black->id]);
        $this->assertSame([$black->id], $product->attributeAssignments()->pluck('product_attribute_value_id')->all());
        app(ProductCatalogService::class)->syncAttributes($product, []);
        $this->assertSame(0, $product->attributeAssignments()->count());
    }

    public function test_single_value_and_duplicate_assignment_rules_are_enforced_beyond_the_form(): void
    {
        $attribute = ProductAttribute::create(['name' => 'اندازه']);
        $small = $attribute->values()->create(['name' => 'کوچک']);
        $large = $attribute->values()->create(['name' => 'بزرگ']);
        $product = Product::factory()->create();
        $this->sync($product, $attribute, [$small->id]);
        foreach ([$small->id, $large->id] as $id) {
            try {
                $product->attributeAssignments()->create(['product_attribute_id' => $attribute->id, 'product_attribute_value_id' => $id]);
                $this->fail('An invalid assignment was accepted.');
            } catch (ValidationException) {
                $this->assertSame(1, $product->attributeAssignments()->count());
            }
        }
    }

    public function test_invalid_full_selection_does_not_destroy_existing_assignments(): void
    {
        $attribute = ProductAttribute::create(['name' => 'اندازه']);
        $small = $attribute->values()->create(['name' => 'کوچک']);
        $large = $attribute->values()->create(['name' => 'بزرگ']);
        $product = Product::factory()->create();
        $this->sync($product, $attribute, [$small->id]);
        foreach ([[$small->id, $large->id], [$small->id, $small->id], [999999]] as $ids) {
            try {
                $this->sync($product, $attribute, $ids);
                $this->fail('An invalid selection was accepted.');
            } catch (ValidationException) {
                $this->assertSame([$small->id], $product->attributeAssignments()->pluck('product_attribute_value_id')->all());
            }
        }
    }

    public function test_values_cannot_be_assigned_under_another_attribute_even_through_sql(): void
    {
        $first = ProductAttribute::create(['name' => 'Color']);
        $second = ProductAttribute::create(['name' => 'Material']);
        $value = $first->values()->create(['name' => 'White']);
        $product = Product::factory()->create();
        try {
            $this->sync($product, $second, [$value->id]);
            $this->fail('Mismatched value accepted.');
        } catch (ValidationException) {
            $this->assertSame(0, $product->attributeAssignments()->count());
        }
        $this->expectException(QueryException::class);
        DB::table('product_attribute_assignments')->insert([
            'product_id' => $product->id, 'product_attribute_id' => $second->id, 'product_attribute_value_id' => $value->id,
        ]);
    }

    public function test_value_labels_normalize_persian_digits_but_separate_attributes_are_independent(): void
    {
        $attribute = ProductAttribute::create(['name' => 'ضخامت', 'unit' => 'mm']);
        $attribute->values()->create(['name' => '۰٫۵']);
        $other = ProductAttribute::create(['name' => 'طول', 'unit' => 'm']);
        $other->values()->create(['name' => '0.5']);
        $this->expectException(ValidationException::class);
        $attribute->values()->create(['name' => '0.5', 'slug' => 'alternate']);
    }

    public function test_numeric_looking_codes_are_not_interpreted_as_numbers(): void
    {
        $attribute = ProductAttribute::create(['name' => 'کد استاندارد']);
        $attribute->values()->create(['name' => '001']);
        $attribute->values()->create(['name' => '1']);
        $this->assertSame(2, $attribute->values()->count());
    }

    public function test_category_hierarchy_paths_and_existing_product_identity_are_preserved(): void
    {
        $root = ProductCategory::factory()->create(['name' => 'پوشاک']);
        $child = ProductCategory::factory()->create(['name' => 'پیراهن', 'parent_id' => $root->id]);
        $leaf = ProductCategory::factory()->create(['name' => 'رسمی', 'parent_id' => $child->id]);
        $product = Product::factory()->create(['product_category_id' => $leaf->id]);
        $slug = $leaf->slug;
        $this->assertTrue($child->parent->is($root));
        $this->assertTrue($root->children->contains($child));
        $this->assertSame('پوشاک / پیراهن / رسمی', ProductCategory::treeOptions()[$leaf->id]);
        $this->assertArrayNotHasKey($leaf->id, ProductCategory::treeOptions($root->id));
        $child->delete();
        $this->assertNull($leaf->refresh()->parent_id);
        $this->assertSame($slug, $leaf->slug);
        $this->assertSame($leaf->id, $product->refresh()->product_category_id);
    }

    public function test_self_parent_and_deep_category_cycles_are_rejected(): void
    {
        $root = ProductCategory::factory()->create();
        $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
        $leaf = ProductCategory::factory()->create(['parent_id' => $child->id]);
        foreach ([$root->id, $leaf->id] as $parentId) {
            try {
                $root->parent_id = $parentId;
                $root->save();
                $this->fail('Cycle accepted.');
            } catch (ValidationException) {
                $this->assertNull($root->fresh()->parent_id);
            }
        }
    }

    public function test_category_change_does_not_remove_attributes_and_freshness_uses_only_direct_category(): void
    {
        app(SettingsService::class)->set('shop_price_validity_days', 30, 'shop', 'number');
        $root = ProductCategory::factory()->create(['price_validity_days' => 5]);
        $child = ProductCategory::factory()->create(['parent_id' => $root->id, 'price_validity_days' => null]);
        $other = ProductCategory::factory()->create(['price_validity_days' => 12]);
        $attribute = ProductAttribute::create(['name' => 'جنس']);
        $value = $attribute->values()->create(['name' => 'فولاد']);
        $child->attributes()->sync([$attribute->id]);
        $product = Product::factory()->create(['product_category_id' => $child->id]);
        $this->sync($product, $attribute, [$value->id]);
        $this->assertSame(30, app(ProductPriceFreshness::class)->days($product));
        $product->update(['product_category_id' => $other->id]);
        $this->assertSame(12, app(ProductPriceFreshness::class)->days($product->fresh()));
        $this->assertSame(1, $product->attributeAssignments()->count());
        $this->assertTrue($attribute->categories->contains($child));
    }

    public function test_deactivation_keeps_existing_assignments_and_blocks_new_ones(): void
    {
        $attribute = ProductAttribute::create(['name' => 'جنس']);
        $value = $attribute->values()->create(['name' => 'فولاد']);
        $product = Product::factory()->create();
        $this->sync($product, $attribute, [$value->id]);
        $value->update(['status' => 'inactive']);
        $attribute->update(['status' => 'inactive']);
        $this->sync($product, $attribute, [$value->id]);
        $this->assertSame(1, $product->attributeAssignments()->count());
        $this->expectException(ValidationException::class);
        $this->sync(Product::factory()->create(), $attribute, [$value->id]);
    }

    public function test_used_values_and_attributes_cannot_be_deleted_and_product_deletion_cleans_assignments(): void
    {
        $attribute = ProductAttribute::create(['name' => 'جنس']);
        $value = $attribute->values()->create(['name' => 'فولاد']);
        $product = Product::factory()->create();
        $this->sync($product, $attribute, [$value->id]);
        foreach ([$value, $attribute] as $record) {
            try {
                $record->delete();
                $this->fail('An in-use record was deleted.');
            } catch (ValidationException) {
                $this->assertTrue($record->fresh()->exists);
            }
        }
        $product->delete();
        $this->assertSame(0, ProductAttributeAssignment::count());
        $value->delete();
        $attribute->delete();
        $this->assertSame(0, ProductAttributeValue::count());
    }

    public function test_used_unit_and_attribute_identity_are_stable_and_multiple_to_single_is_guarded(): void
    {
        $attribute = ProductAttribute::create(['name' => 'طول', 'unit' => 'mm', 'is_multiple' => true]);
        $first = $attribute->values()->create(['name' => '1']);
        $second = $attribute->values()->create(['name' => '2']);
        $this->sync(Product::factory()->create(), $attribute, [$first->id, $second->id]);
        foreach ([['slug' => 'renamed'], ['unit' => 'cm'], ['is_multiple' => false]] as $changes) {
            try {
                $attribute->fresh()->update($changes);
                $this->fail('Unsafe contract change accepted.');
            } catch (ValidationException) {
                $this->assertSame('mm', $attribute->fresh()->unit);
                $this->assertTrue($attribute->fresh()->is_multiple);
            }
        }
    }

    public function test_catalog_admin_permissions_and_in_use_delete_authorization(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->create();
        $inactive = User::factory()->admin()->create(['status' => 'inactive']);
        $attribute = ProductAttribute::create(['name' => 'Material']);
        $value = $attribute->values()->create(['name' => 'Steel']);
        $this->assertTrue(Gate::forUser($admin)->allows('create', ProductBrand::class));
        $this->assertFalse(Gate::forUser($client)->allows('create', ProductBrand::class));
        $this->assertFalse(Gate::forUser($inactive)->allows('create', ProductTag::class));
        $this->sync(Product::factory()->create(), $attribute, [$value->id]);
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $value));
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');
        $this->assertFalse(Gate::forUser($admin)->allows('viewAny', ProductAttribute::class));
    }

    private function sync(Product $product, ProductAttribute $attribute, array $ids): void
    {
        app(ProductCatalogService::class)->syncAttributes($product, [['attribute_id' => $attribute->id, 'value_ids' => $ids]]);
    }
}
