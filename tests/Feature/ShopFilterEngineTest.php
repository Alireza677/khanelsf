<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductTag;
use App\Models\Template;
use App\Services\ProductCatalogService;
use App\Services\ProductPriceFreshness;
use App\Services\ProductPricingService;
use App\Services\ProductQueryService;
use App\Services\SettingsService;
use App\Services\ShopArchiveService;
use App\Services\ShopCategoryTree;
use App\Services\ShopFilterParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopFilterEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_values_are_or_and_different_attributes_are_and_without_duplicate_products(): void
    {
        $material = ProductAttribute::create(['name' => 'جنس', 'slug' => 'material', 'is_filterable' => true, 'is_multiple' => true]);
        $steel = $material->values()->create(['name' => 'فولاد', 'slug' => 'steel']);
        $aluminium = $material->values()->create(['name' => 'آلومینیوم', 'slug' => 'aluminium']);
        $size = ProductAttribute::create(['name' => 'اندازه', 'slug' => 'size', 'is_filterable' => true]);
        $small = $size->values()->create(['name' => 'کوچک', 'slug' => 'small']);
        $large = $size->values()->create(['name' => 'بزرگ', 'slug' => 'large']);
        $first = $this->product('First');
        $second = $this->product('Second');
        $third = $this->product('Third');
        $this->assign($first, [$material->id => [$steel->id, $aluminium->id], $size->id => [$small->id]]);
        $this->assign($second, [$material->id => [$aluminium->id], $size->id => [$small->id]]);
        $this->assign($third, [$material->id => [$steel->id], $size->id => [$large->id]]);
        $this->assertEqualsCanonicalizing([$first->id, $third->id], $this->ids(['attributes' => ['material' => ['steel']]]));
        $this->assertEqualsCanonicalizing([$first->id, $second->id, $third->id], $this->ids(['attributes' => ['material' => ['steel', 'aluminium']]]));
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->ids(['attributes' => ['material' => ['steel', 'aluminium'], 'size' => ['small']]]));
    }

    public function test_category_brand_tag_attribute_price_stock_and_search_compose(): void
    {
        $parent = ProductCategory::factory()->create(['slug' => 'parent']);
        $child = ProductCategory::factory()->create(['slug' => 'child', 'parent_id' => $parent->id]);
        $brand = ProductBrand::create(['name' => 'Acme', 'slug' => 'acme']);
        $tag = ProductTag::create(['name' => 'Selected', 'slug' => 'selected']);
        $attribute = ProductAttribute::create(['name' => 'Material', 'slug' => 'material', 'is_filterable' => true]);
        $value = $attribute->values()->create(['name' => 'Steel', 'slug' => 'steel']);
        $target = $this->product('Target steel', ['product_category_id' => $child->id, 'product_brand_id' => $brand->id, 'price' => '200.00', 'sale_price' => '80.25']);
        $target->tags()->sync([$tag->id]);
        $this->assign($target, [$attribute->id => [$value->id]]);
        $this->product('Target wrong price', ['product_category_id' => $parent->id, 'price' => '300.00']);
        $query = ['category' => 'parent', 'brands' => ['acme'], 'tags' => ['selected'], 'attributes' => ['material' => ['steel']],
            'q' => 'Target', 'min_price' => '80.00', 'max_price' => '90.00', 'stock' => 'in_stock', 'on_sale' => '1'];
        $this->assertSame([$target->id], $this->ids($query));
        $this->assertSame([], $this->ids(['category' => 'parent', 'include_children' => '0', 'brands' => ['acme']]));
        $this->get($this->url($query))->assertOk()->assertSee('جستجو: Target')->assertSee('Acme');
    }

    public function test_multiple_brands_and_tags_use_or_within_their_dimension(): void
    {
        $brands = [ProductBrand::create(['name' => 'A', 'slug' => 'a']), ProductBrand::create(['name' => 'B', 'slug' => 'b'])];
        $tags = [ProductTag::create(['name' => 'X', 'slug' => 'x']), ProductTag::create(['name' => 'Y', 'slug' => 'y'])];
        $first = $this->product('First', ['product_brand_id' => $brands[0]->id]);
        $second = $this->product('Second', ['product_brand_id' => $brands[1]->id]);
        $first->tags()->sync([$tags[0]->id, $tags[1]->id]);
        $second->tags()->sync([$tags[1]->id]);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $this->ids(['brands' => ['a', 'b'], 'tags' => ['x', 'y']]));
    }

    public function test_effective_sql_price_matches_php_including_zero_equal_and_higher_sale_prices(): void
    {
        $pricing = app(ProductPricingService::class);
        foreach ([['20.25', null], ['20.25', '10.10'], ['20.25', '0.00'], ['20.25', '20.25'], ['20.25', '30.00'], ['0.00', null], ['9999999999.99', null]] as $index => [$price, $sale]) {
            $this->product('Price '.$index, ['price' => $price, 'sale_price' => $sale]);
        }
        foreach (Product::query()->select('products.*')->selectRaw($pricing->effectivePriceSql().' AS catalog_price')->get() as $product) {
            $this->assertSame($pricing->exactPrice($product), $pricing->amount($product->getAttribute('catalog_price')));
        }
        $zero = $this->ids(['min_price' => '0.00', 'max_price' => '0.00']);
        $this->assertCount(2, $zero);
        $this->assertCount(1, $this->ids(['min_price' => '10.10', 'max_price' => '10.10']));
        $sorted = $this->ids(['sort' => 'price_asc']);
        $reversed = $this->ids(['sort' => 'price_desc']);
        $this->assertSame('0.00', $pricing->exactPrice(Product::find($sorted[0])));
        $this->assertSame('9999999999.99', $pricing->exactPrice(Product::find($reversed[0])));
    }

    public function test_sale_featured_and_inventory_are_independent_and_invalid_amounts_do_not_render_as_valid_prices(): void
    {
        $sale = $this->product('Sale', ['sale_price' => '50.00']);
        $featured = $this->product('Featured', ['is_featured' => true]);
        $unavailable = $this->product('Unavailable', ['has_stock' => false]);
        $invalid = $this->product('Invalid');
        DB::table('products')->where('id', $invalid->id)->update(['sale_price' => -1]);
        $this->assertSame([$sale->id], $this->ids(['on_sale' => '1']));
        $this->assertSame([$featured->id], $this->ids(['featured' => '1']));
        $this->assertSame([$unavailable->id], $this->ids(['stock' => 'out_of_stock']));
        $this->assertNotContains($invalid->id, $this->ids());
    }

    public function test_parent_subtree_and_inactive_branches_are_consistent_without_changing_product_urls(): void
    {
        $root = ProductCategory::factory()->create(['slug' => 'root']);
        $child = ProductCategory::factory()->create(['parent_id' => $root->id, 'slug' => 'child']);
        $leaf = ProductCategory::factory()->create(['parent_id' => $child->id, 'slug' => 'leaf']);
        $direct = $this->product('Root product', ['product_category_id' => $root->id]);
        $deep = $this->product('Deep product', ['product_category_id' => $leaf->id]);
        $this->assertEqualsCanonicalizing([$direct->id, $deep->id], $this->ids(['category' => 'root']));
        $this->get($this->url(['category' => 'root', 'include_children' => '0']))->assertOk()
            ->assertSee('Root product')->assertDontSee('Deep product');
        $child->update(['status' => 'inactive']);
        $this->assertSame([$direct->id], $this->ids(['category' => 'root']));
        $this->get(route('shop.category', 'leaf'))->assertNotFound();
        $this->get(route('shop.category', 'child').'?category=root')->assertNotFound();
        $this->get(route('shop.show', $deep->slug))->assertOk();
        $this->get(route('sitemap'))->assertOk()->assertDontSee(route('shop.category', 'leaf'));
    }

    public function test_inactive_or_nonfilterable_attributes_and_values_are_not_public_filters_and_assignments_are_kept(): void
    {
        $attribute = ProductAttribute::create(['name' => 'Material', 'slug' => 'material', 'is_filterable' => true]);
        $value = $attribute->values()->create(['name' => 'Steel', 'slug' => 'steel']);
        $product = $this->product('Product');
        $this->assign($product, [$attribute->id => [$value->id]]);
        $url = $this->url(['attributes' => ['material' => ['steel']]]);
        $this->get($url)->assertOk();
        $value->update(['status' => 'inactive']);
        $this->get($url)->assertNotFound();
        $value->update(['status' => 'active']);
        $attribute->update(['is_filterable' => false]);
        $this->get($url)->assertNotFound();
        $attribute->update(['is_filterable' => true, 'status' => 'inactive']);
        $this->get($url)->assertNotFound();
        $this->assertSame(1, $product->attributeAssignments()->count());
    }

    public function test_facets_only_show_active_choices_with_products_in_the_base_category_context(): void
    {
        $firstCategory = ProductCategory::factory()->create(['slug' => 'one']);
        $secondCategory = ProductCategory::factory()->create(['slug' => 'two']);
        $firstBrand = ProductBrand::create(['name' => 'Relevant brand', 'slug' => 'relevant']);
        $secondBrand = ProductBrand::create(['name' => 'Other brand', 'slug' => 'other']);
        ProductBrand::create(['name' => 'Unused brand']);
        $this->product('One', ['product_category_id' => $firstCategory->id, 'product_brand_id' => $firstBrand->id]);
        $this->product('Two', ['product_category_id' => $secondCategory->id, 'product_brand_id' => $secondBrand->id]);
        $response = $this->get(route('shop.category', 'one'))->assertOk();
        $this->assertSame(['relevant'], $response->viewData('filterUi')['facets']['brands']->pluck('slug')->all());
        $response->assertDontSee('Other brand')->assertDontSee('Unused brand');
    }

    public function test_pagination_count_is_distinct_and_all_sorts_have_stable_ties(): void
    {
        app(SettingsService::class)->set('shop_per_page', 2, 'shop', 'number');
        $tagA = ProductTag::create(['name' => 'A', 'slug' => 'a']);
        $tagB = ProductTag::create(['name' => 'B', 'slug' => 'b']);
        foreach (range(1, 5) as $number) {
            $product = $this->product('Same title', ['price' => '100.00']);
            $product->tags()->sync([$tagA->id, $tagB->id]);
        }
        $first = $this->get($this->url(['tags' => ['a', 'b'], 'sort' => 'price_asc']))->assertOk()->viewData('products');
        $second = $this->get($this->url(['tags' => ['a', 'b'], 'sort' => 'price_asc', 'page' => 2]))->assertOk()->viewData('products');
        $this->assertSame(5, $first->total());
        $this->assertCount(2, $first->items());
        $this->assertSame([], array_intersect($first->pluck('id')->all(), $second->pluck('id')->all()));
        $this->assertStringContainsString('tags%5B0%5D=a', $first->nextPageUrl());
        foreach (['default', 'newest', 'oldest', 'price_asc', 'price_desc', 'title'] as $sort) {
            $ids = $this->ids(['sort' => $sort]);
            $this->assertSame($ids, $this->ids(['sort' => $sort]));
            $this->assertCount(5, array_unique($ids));
        }
    }

    public function test_text_search_escapes_sql_wildcards_and_respects_publication(): void
    {
        $literal = $this->product('Literal %_ marker');
        $this->product('Other marker');
        $this->product('Draft %_', ['status' => 'draft']);
        $this->product('Future %_', ['published_at' => now()->addDay()]);
        $this->assertSame([$literal->id], $this->ids(['q' => '%_']));
    }

    public function test_normalized_urls_legacy_category_aliases_and_empty_results_have_explicit_statuses(): void
    {
        ProductCategory::factory()->create(['slug' => 'category']);
        $this->get(route('shop.index', ['category' => 'category', 'unused' => 'x']))->assertStatus(301)->assertRedirect(route('shop.category', 'category'));
        $this->get(route('shop.index').'?sort=default&page=1&thickness[]=0.5')->assertStatus(301)->assertRedirect(route('shop.index'));
        $this->get(route('shop.index', ['brands' => ['missing']]))->assertNotFound();
        $this->get($this->url(['q' => 'not-found']))->assertOk()->assertSee('محصولی با شرایط انتخاب‌شده پیدا نشد')->assertSee('پاک کردن همه');
        $this->get($this->url(['page' => 99]))->assertNotFound();
        $this->get(route('shop.index'))->assertOk();
        $this->get(route('shop.category', 'category').'?category=')->assertStatus(301)->assertRedirect(route('shop.index'));
    }

    public function test_seo_distinguishes_filters_from_unfiltered_pagination_and_keeps_category_robots(): void
    {
        app(SettingsService::class)->set('shop_per_page', 1, 'shop', 'number');
        $category = ProductCategory::factory()->create(['slug' => 'category']);
        $this->product('First', ['product_category_id' => $category->id]);
        $this->product('Second', ['product_category_id' => $category->id]);
        $url = $this->url(['page' => 2]);
        $response = $this->get($url)->assertOk()->assertSee('<link rel="canonical" href="'.$url.'">', false)->assertSee('content="index, follow"', false);
        $this->assertSame($url, $response->viewData('seo')->schema['url']);
        $filtered = $this->url(['q' => 'First']);
        $this->get($filtered)->assertOk()->assertSee('<link rel="canonical" href="'.$filtered.'">', false)->assertSee('content="noindex, follow"', false);
        $categoryUrl = $this->url(['category' => 'category', 'page' => 2]);
        $this->get($categoryUrl)->assertOk()->assertSee('<link rel="canonical" href="'.$categoryUrl.'">', false);
        $category->update(['robots_index' => false]);
        $this->get(route('shop.category', 'category'))->assertOk()->assertSee('content="noindex, follow"', false);
        $this->get(route('sitemap'))->assertOk()->assertDontSee('?q=')->assertDontSee('?page=');
    }

    public function test_dynamic_template_renders_real_generic_controls_and_preserves_category_context(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'category']);
        $attribute = ProductAttribute::create(['name' => 'ویژگی عمومی دلخواه', 'slug' => 'custom', 'is_filterable' => true]);
        $value = $attribute->values()->create(['name' => 'مقدار نمونه', 'slug' => 'sample']);
        $product = $this->product('Sample', ['product_category_id' => $category->id]);
        $this->assign($product, [$attribute->id => [$value->id]]);
        Template::create(['title' => 'Shop', 'slug' => 'shop-template', 'type' => 'product_category', 'status' => 'published', 'is_default' => true,
            'conditions' => ['type' => 'all'], 'blocks' => [['type' => 'template_shop_complete', 'data' => []]]]);
        $response = $this->get(route('shop.category', 'category'))->assertOk();
        $response->assertSee('name="attributes[custom][]"', false)->assertSee('ویژگی عمومی دلخواه')->assertSee('مقدار نمونه')
            ->assertSee('name="sort"', false)->assertSee('data-shop-filter-drawer', false)
            ->assertDontSee('name="thickness[]"', false)->assertDontSee('name="application[]"', false)->assertDontSee('پربازدیدترین');
        $this->assertSame(route('shop.category', 'category'), $response->viewData('filterUi')['clearUrl']);
    }

    public function test_expired_price_is_still_the_same_registered_price_and_module_disable_remains_enforced(): void
    {
        app(SettingsService::class)->set('shop_price_freshness_enabled', true, 'shop', 'boolean');
        $product = $this->product('Expired', ['price_verified_at' => null]);
        $this->assertSame([$product->id], $this->ids(['min_price' => '100.00', 'max_price' => '100.00']));
        $this->get($this->url(['min_price' => '100.00']))->assertOk()->assertSee('آخرین قیمت ثبت‌شده');
        $this->assertFalse(app(ProductPriceFreshness::class)->allowsPurchase($product));
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');
        $this->get(route('shop.index'))->assertNotFound();
        $this->get(route('shop.category', $product->category->slug))->assertNotFound();
    }

    public function test_unicode_identifiers_and_duplicate_choices_have_one_reproducible_url(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'پوشاک']);
        $attribute = ProductAttribute::create(['name' => 'رنگ', 'slug' => 'رنگ', 'is_filterable' => true]);
        $value = $attribute->values()->create(['name' => 'سفید', 'slug' => 'سفید']);
        $product = $this->product('محصول فارسی', ['product_category_id' => $category->id]);
        $this->assign($product, [$attribute->id => [$value->id]]);
        $url = $this->url(['category' => 'پوشاک', 'attributes' => ['رنگ' => ['سفید']]]);
        $this->get($url)->assertOk()->assertSee('محصول فارسی');
        $duplicate = route('shop.category', 'پوشاک').'?'.http_build_query(['attributes' => ['رنگ' => ['سفید', 'سفید']]], '', '&', PHP_QUERY_RFC3986);
        $this->get($duplicate)->assertStatus(301)->assertRedirect($url);
    }

    public function test_selected_choices_outside_current_facets_remain_removable_and_are_not_silently_dropped(): void
    {
        $brand = ProductBrand::create(['name' => 'Selected brand', 'slug' => 'selected']);
        $this->product('Other product', ['product_brand_id' => $brand->id]);
        $response = $this->get($this->url(['brands' => ['selected'], 'q' => 'no-match']))->assertOk();
        $ui = $response->viewData('filterUi');
        $this->assertTrue($ui['facets']['brands']->isEmpty());
        $this->assertContains(['name' => 'brands[]', 'value' => 'selected'], $ui['filterHidden']);
        $this->assertContains('برند: Selected brand', array_column($ui['active'], 'label'));
    }

    private function product(string $title, array $attributes = []): Product
    {
        return Product::factory()->published()->create(['title' => $title, 'price' => '100.00', ...$attributes]);
    }

    private function assign(Product $product, array $attributes): void
    {
        $rows = [];
        foreach ($attributes as $id => $values) {
            $rows[] = ['attribute_id' => $id, 'value_ids' => $values];
        }
        app(ProductCatalogService::class)->syncAttributes($product, $rows);
    }

    private function ids(array $query = []): array
    {
        $filters = app(ShopFilterParser::class)->parse($query);
        $tree = app(ShopCategoryTree::class);
        app(ShopArchiveService::class)->validate($filters, $tree);
        $products = app(ProductQueryService::class);

        return $products->sortArchive($products->archive($filters, $tree), $filters->data['sort'])->pluck('products.id')->all();
    }

    private function url(array $query): string
    {
        return app(ShopFilterParser::class)->parse($query)->url(withPage: true);
    }
}
