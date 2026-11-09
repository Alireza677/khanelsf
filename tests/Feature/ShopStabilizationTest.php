<?php

namespace Tests\Feature;

use App\Filament\Resources\TemplateResource;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductTag;
use App\Models\Template;
use App\Models\User;
use App\Services\ProductCatalogService;
use App\Services\SettingsService;
use App\Services\ShopFilterParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class ShopStabilizationTest extends TestCase
{
    use RefreshDatabase;

    private array $mediaLibraryCache = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['mediaLibraryImageItemsCache', 'mediaLibraryVideoItemsCache'] as $name) {
            $property = new ReflectionProperty(TemplateResource::class, $name);
            $this->mediaLibraryCache[$name] = $property->getValue();
            $property->setValue(null, null);
        }
    }

    protected function tearDown(): void
    {
        // The existing editor caches are process-static, while each test has a new database.
        foreach ($this->mediaLibraryCache as $name => $value) {
            (new ReflectionProperty(TemplateResource::class, $name))->setValue(null, $value);
        }
        parent::tearDown();
    }

    public function test_editor_saved_products_title_renders_once_as_the_existing_accessible_section_heading(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $template = $this->template();
        $component = Livewire::actingAs($admin)->test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $key = array_key_first($component->get('data.blocks'));
        $component->set("data.blocks.{$key}.data.products_title", 'فهرست انتخابی مدیر')->call('save')->assertHasNoFormErrors();
        $this->assertSame('فهرست انتخابی مدیر', $template->fresh()->blocks[0]['data']['products_title']);
        $html = $this->get(route('shop.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'فهرست انتخابی مدیر'));
        $this->assertStringContainsString('<h2 id="shop-template-products-title" class="sr-only">فهرست انتخابی مدیر</h2>', $html);
        $this->assertSame(1, preg_match_all('/<h1\b/', $html));
    }

    public function test_missing_null_and_blank_products_titles_keep_the_legacy_heading_in_public_and_preview(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->template();
        foreach ([[], ['products_title' => null], ['products_title' => '   ']] as $data) {
            $template->update(['blocks' => [['type' => 'template_shop_complete', 'data' => $data]]]);
            foreach ([route('shop.index'), route('admin.preview.templates.show', $template)] as $url) {
                $this->actingAs($admin)->get($url)->assertOk()
                    ->assertSee('<h2 id="shop-template-products-title" class="sr-only">محصولات فروشگاه</h2>', false);
            }
        }
    }

    public function test_hero_accepts_two_hundred_unicode_characters_and_server_rejects_more(): void
    {
        $this->template();
        $query = str_repeat('محصول', 40);
        Product::factory()->published()->create(['title' => $query, 'is_featured' => true]);
        $filters = app(ShopFilterParser::class)->parse(['q' => $query, 'featured' => '1']);
        $response = $this->get($filters->url())->assertOk();
        $this->assertMatchesRegularExpression('/<input\s+id="shop-template-q"[^>]*maxlength="200"[^>]*name="q"|<input\s+id="shop-template-q"[^>]*name="q"[^>]*maxlength="200"/s', $response->getContent());
        $this->assertSame($query, $response->viewData('filters')['q']);
        $this->assertContains(['name' => 'featured', 'value' => '1'], $response->viewData('filterUi')['searchHidden']);
        $this->get(route('shop.index').'?q='.rawurlencode($query.'ی'))->assertNotFound();
        $this->get(route('shop.index').'?q='.rawurlencode(str_repeat('😀', 201)))->assertNotFound();
    }

    public function test_draft_shop_preview_uses_the_public_catalog_context_without_exposing_private_products(): void
    {
        $admin = User::factory()->admin()->create();
        $category = ProductCategory::factory()->create(['slug' => 'catalog']);
        $brand = ProductBrand::create(['name' => 'برند نمونه', 'slug' => 'brand']);
        $tag = ProductTag::create(['name' => 'برچسب نمونه', 'slug' => 'tag']);
        $attribute = ProductAttribute::create(['name' => 'رنگ', 'slug' => 'color', 'is_filterable' => true]);
        $value = $attribute->values()->create(['name' => 'سفید', 'slug' => 'white']);
        $product = Product::factory()->published()->create(['title' => 'محصول عمومی', 'product_category_id' => $category->id, 'product_brand_id' => $brand->id]);
        $product->tags()->sync([$tag->id]);
        app(ProductCatalogService::class)->syncAttributes($product, [['attribute_id' => $attribute->id, 'value_ids' => [$value->id]]]);
        Product::factory()->create(['title' => 'محصول پیش‌نویس محرمانه', 'status' => 'draft']);
        Product::factory()->published()->create(['title' => 'محصول آینده محرمانه', 'published_at' => now()->addDay()]);
        $template = $this->template(['status' => 'draft']);
        $query = ['brands' => ['brand'], 'tags' => ['tag'], 'attributes' => ['color' => ['white']]];
        $public = $this->get(app(ShopFilterParser::class)->parse($query)->url())->assertOk();
        $preview = $this->actingAs($admin)->get(route('admin.preview.templates.show', ['template' => $template, ...$query]))
            ->assertOk()->assertSee('محصول عمومی')->assertSee('برند نمونه')->assertSee('برچسب نمونه')->assertSee('سفید')
            ->assertSee('name="attributes[color][]"', false)->assertSee('content="noindex, nofollow"', false)
            ->assertDontSee('محصول پیش‌نویس محرمانه')->assertDontSee('محصول آینده محرمانه');
        $context = $preview->viewData('templateContext');
        $this->assertSame($public->viewData('products')->modelKeys(), $context['items']->modelKeys());
        $this->assertSame($public->viewData('filters'), $context['filters']);
        $this->assertSame($public->viewData('filterUi')['categoryUrls'], $context['filterUi']['categoryUrls']);
        $this->assertSame($public->viewData('filterUi')['active'], $context['filterUi']['active']);
        $this->assertSame($public->viewData('categories')->modelKeys(), $context['categories']->modelKeys());
        $this->assertContains($category->id, $context['categories']->modelKeys());
        $this->assertTrue($context['isPreview']);
        $this->assertSame('draft', $template->fresh()->status);
        $this->assertNull($public->viewData('template'));
    }

    public function test_preview_keeps_authorization_and_rejects_invalid_public_filter_selections(): void
    {
        $template = $this->template(['status' => 'draft']);
        $url = route('admin.preview.templates.show', $template);
        $this->get($url)->assertNotFound();
        $this->actingAs(User::factory()->create(['is_admin' => false]))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get($url.'?brands[]=missing')->assertNotFound();
    }

    public function test_category_preview_and_fallback_share_subtree_filters_pagination_and_seo(): void
    {
        app(SettingsService::class)->set('shop_per_page', 1, 'shop', 'number');
        $category = ProductCategory::factory()->create(['slug' => 'parent']);
        $child = ProductCategory::factory()->create(['parent_id' => $category->id]);
        Product::factory()->published()->count(2)->create(['product_category_id' => $child->id]);
        Product::factory()->published()->create(['title' => 'خارج از دسته', 'product_category_id' => null]);
        $template = $this->template(['type' => 'product_category', 'status' => 'draft']);
        $filters = app(ShopFilterParser::class)->parse(['page' => '2'], 'parent');
        $public = $this->get($filters->url(withPage: true))->assertOk()->assertViewIs('shop.category')
            ->assertSee('<link rel="canonical" href="'.$filters->url(withPage: true).'">', false);
        $preview = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.templates.show', ['template' => $template, 'context_id' => $category->id, 'page' => 2]))->assertOk();
        $this->assertSame($public->viewData('products')->modelKeys(), $preview->viewData('products')->modelKeys());
        $this->assertSame(2, $preview->viewData('products')->total());
        $this->assertSame('parent', $preview->viewData('filters')['category']);
        $this->assertSame($public->viewData('filterUi')['categoryLabels'], $preview->viewData('filterUi')['categoryLabels']);
        $this->get(route('shop.category', 'parent').'?include_children=0')->assertOk()->assertSee('0 محصول');
        $this->get(route('shop.category', 'parent').'?page=3')->assertNotFound();
        $category->update(['status' => 'inactive']);
        $this->get(route('admin.preview.templates.show', ['template' => $template, 'context_id' => $category->id]))->assertNotFound();
    }

    public function test_valid_empty_filters_return_200_with_self_canonical_noindex_and_removal_controls(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'empty']);
        foreach (['shop_index', 'product_category'] as $type) {
            $this->template(['type' => $type, 'slug' => $type]);
            $filters = app(ShopFilterParser::class)->parse(['q' => 'بدون نتیجه', 'stock' => 'in_stock'], $type === 'product_category' ? $category->slug : null);
            $response = $this->get($filters->url())->assertOk()->assertSee('0 محصول')
                ->assertSee('محصولی با شرایط انتخاب‌شده پیدا نشد')->assertSee('پاک کردن همه')
                ->assertSee('<link rel="canonical" href="'.e($filters->url()).'">', false)
                ->assertSee('content="noindex, follow"', false);
            $this->assertSame($filters->url(), $response->viewData('seo')->schema['url']);
            $page = app(ShopFilterParser::class)->parse([...$filters->query(), 'page' => 2], $filters->data['category']);
            $this->get($page->url(withPage: true))->assertNotFound();
        }
        $this->get(route('shop.category', 'missing'))->assertNotFound();
        $this->get(route('shop.index').'?stock=invalid')->assertNotFound();
        $this->get(route('shop.index').'?brands[]=missing')->assertNotFound();
    }

    public function test_fallback_stock_copy_is_persian_and_preview_does_not_change_saved_template(): void
    {
        Product::factory()->published()->create(['stock_status' => 'out_of_stock']);
        $this->get(route('shop.index'))->assertOk()->assertSee('ناموجود')->assertDontSee('Out of stock');
        $template = $this->template(['status' => 'draft'], ['category_label' => 'Categories', 'products_title' => 'عنوان مدیر']);
        $before = $template->fresh()->getRawOriginal();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.preview.templates.show', $template))
            ->assertOk()->assertSee('Categories')->assertSee('عنوان مدیر');
        $this->assertSame($before, $template->fresh()->getRawOriginal());
    }

    public function test_new_shop_block_defaults_are_persian_without_replacing_legacy_saved_labels(): void
    {
        $method = new ReflectionMethod(TemplateResource::class, 'blockDefinitions');
        $block = collect($method->invoke(null, 'shop_index'))->first(fn ($block) => $block->getName() === 'template_shop_complete');
        $fields = collect($block->getChildComponents())->keyBy(fn ($field) => $field->getName());
        foreach ([
            'search_placeholder' => 'جستجو در محصولات', 'category_label' => 'انتخاب دسته‌بندی',
            'category_section_title' => 'خرید بر اساس دسته‌بندی', 'products_title' => 'محصولات',
            'empty_message' => 'محصولی با شرایط انتخاب‌شده پیدا نشد.',
        ] as $key => $value) {
            $this->assertSame($value, $fields[$key]->getDefaultState());
        }
    }

    public function test_admin_preview_keeps_session_favorites_and_remains_available_when_public_shop_is_disabled(): void
    {
        $admin = User::factory()->admin()->create();
        $favorite = Product::factory()->published()->create(['product_category_id' => null]);
        Product::factory()->published()->create(['product_category_id' => null]);
        $template = $this->template(['status' => 'draft']);
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');
        $this->get(route('shop.index'))->assertNotFound();
        $response = $this->actingAs($admin)->withSession(['shop.favorite_product_ids' => [$favorite->id]])
            ->get(route('admin.preview.templates.show', ['template' => $template, 'favorites' => 1]))->assertOk();
        $this->assertSame([$favorite->id], $response->viewData('products')->modelKeys());
        $response->assertSee('content="noindex, nofollow"', false);
        $this->get(route('admin.preview.templates.show', ['template' => $template, 'favorites' => 1, 'page' => 2]))->assertNotFound();
    }

    public function test_preview_eager_loads_media_without_catalog_queries_growing_per_product(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $template = $this->template(['status' => 'draft']);
        Product::factory()->published()->count(3)->create(['product_category_id' => null]);
        $url = route('admin.preview.templates.show', $template);
        $measure = function () use ($url): array {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->get($url)->assertOk();
            $count = count(array_filter(DB::getQueryLog(), fn ($entry) => ! preg_match('/from ["`]settings["`]/i', $entry['query'])));
            DB::disableQueryLog();
            foreach ($response->viewData('products') as $product) {
                $this->assertTrue($product->relationLoaded('category'));
                $this->assertTrue($product->relationLoaded('media'));
                $this->assertTrue($product->relationLoaded('mediaUsages'));
            }

            return [$count, $response->viewData('products')->count()];
        };
        $this->get($url)->assertOk();
        [$first, $firstItems] = $measure();
        Product::factory()->published()->count(6)->create(['product_category_id' => null]);
        [$second, $secondItems] = $measure();
        $this->assertSame(3, $firstItems);
        $this->assertSame(9, $secondItems);
        $this->assertLessThanOrEqual($first + 1, $second);
    }

    private function template(array $overrides = [], array $data = []): Template
    {
        return Template::create([
            'title' => 'Shop stabilization', 'slug' => 'shop-stabilization', 'type' => 'shop_index',
            'status' => 'published', 'is_default' => true, 'priority' => 10, 'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'template_shop_complete', 'data' => $data]],
            ...$overrides,
        ]);
    }
}
