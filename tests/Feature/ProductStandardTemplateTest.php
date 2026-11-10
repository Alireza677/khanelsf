<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockEditorSaveManager;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Blocks\FeatureGrid\FeatureGridDataNormalizer;
use App\CMS\Blocks\Product\ProductSummaryBlock;
use App\CMS\Templates\Recipes\TemplateRecipeCompatibilityValidator;
use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\CMS\Templates\Recipes\TemplateRecipeRegistry;
use App\CMS\Templates\TemplatePublicationValidator;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Form;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\Template;
use App\Models\User;
use App\Services\ProductCatalogService;
use App\Services\ProductPriceFreshness;
use App\Services\ProductTemplateContextBuilder;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductStandardTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function standard(bool $published = true): Template
    {
        $template = app(TemplateRecipeInstantiator::class)->createDraft('product-standard-v1', ['is_default' => true]);
        if ($published) {
            $template->update(['status' => 'published']);
        }

        return $template;
    }

    public function test_recipe_registry_save_boundary_and_publication_use_canonical_product_blocks(): void
    {
        $recipe = app(TemplateRecipeRegistry::class)->find('product-standard-v1');
        $this->assertSame([], app(TemplateRecipeCompatibilityValidator::class)->validate($recipe));
        $first = app(TemplateRecipeInstantiator::class)->makeDraft($recipe->key());
        $second = app(TemplateRecipeInstantiator::class)->makeDraft($recipe->key());
        $this->assertSame('draft', $first->status);
        $this->assertFalse($first->is_default);
        $this->assertSame(['product_summary', 'feature_grid', 'product_details', 'product_related', 'cta'], array_column($first->blocks, 'type'));
        $this->assertSame([], app(TemplatePublicationValidator::class)->validate($first->toArray()));
        $this->assertSame($first->blocks, app(BlockEditorSaveManager::class)->prepare($first->blocks, false));
        $this->assertEmpty(array_intersect(array_column(array_column($first->blocks, 'data'), 'block_id'), array_column(array_column($second->blocks, 'data'), 'block_id')));
        $registry = app(BlockRegistry::class);
        foreach (['product_summary', 'product_details'] as $type) {
            $this->assertTrue($registry->supportsTemplateTarget($type, 'product_single'));
            $this->assertFalse($registry->supportsTemplateTarget($type, 'service_single'));
        }
        $summary = app(ProductSummaryBlock::class)->normalize(['settings' => ['highlight_attribute_ids' => ['1', 1, -1, 'x']]]);
        $this->assertSame([1], $summary['settings']['highlight_attribute_ids']);
        $this->assertSame($summary, app(ProductSummaryBlock::class)->normalize($summary));
    }

    public function test_editor_round_trip_preserves_identity_settings_and_guide(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $template = $this->standard(false);
        $component = Livewire::test(EditTemplate::class, ['record' => $template->id]);
        $state = $component->get('data')['blocks'];
        $keys = collect($state)->mapWithKeys(fn ($block, $key) => [$block['type'] => $key]);
        $ids = collect($state)->pluck('data.block_id', 'type')->all();
        $component->set("data.blocks.{$keys['product_summary']}.data.settings.show_share", false)
            ->set("data.blocks.{$keys['product_details']}.data.content.buying_guide", '<p>راهنمای معتبر فروشگاه</p>')
            ->set("data.blocks.{$keys['product_details']}.data.settings.default_tab", 'guide')
            ->call('save')->assertHasNoFormErrors();
        $saved = collect($template->fresh()->blocks)->keyBy('type');
        $this->assertFalse(data_get($saved, 'product_summary.data.settings.show_share'));
        $this->assertSame('guide', data_get($saved, 'product_details.data.settings.default_tab'));
        $this->assertSame($ids, $saved->pluck('data.block_id', 'type')->all());
        $this->assertSame([], app(TemplatePublicationValidator::class)->validate($template->fresh()->toArray()));
    }

    public function test_minimal_product_has_one_title_no_invented_features_and_optional_sections_are_absent(): void
    {
        $this->standard();
        $product = Product::factory()->published()->create(['title' => 'محصول ساده عمومی', 'content' => null, 'excerpt' => null]);
        $response = $this->get(route('shop.show', $product->slug))->assertOk()
            ->assertSee('product-standard')->assertSee('تصویر محصول موجود نیست')
            ->assertSee('تعداد صحیح از ۱ تا ۹۹')->assertSee(route('cart.add', $product), false)
            ->assertDontSee('data-product-tabs', false)->assertDontSee('data-feature-grid-section', false)
            ->assertDontSee('block-cta__', false)->assertDontSee('قیمت هر متر')->assertDontSee('درخواست پیش‌فاکتور')
            ->assertDontSee('نظرات مشتریان')->assertDontSee('aggregateRating');
        $this->assertSame(1, preg_match_all('/<h1\b/', $response->getContent()));
        $this->assertSame(1, substr_count($response->getContent(), '>محصول ساده عمومی</h1>'));
    }

    public function test_context_uses_visible_hierarchy_active_catalog_and_ordered_deduplicated_shared_media(): void
    {
        Storage::fake('public');
        $root = ProductCategory::factory()->create(['name' => 'ریشه']);
        $child = ProductCategory::factory()->create(['name' => 'فرزند', 'parent_id' => $root->id]);
        $brand = ProductBrand::create(['name' => 'برند واقعی']);
        $product = Product::factory()->published()->create(['product_category_id' => $child->id, 'product_brand_id' => $brand->id]);
        $attribute = ProductAttribute::create(['name' => 'اندازه', 'unit' => 'mm', 'is_multiple' => true, 'is_filterable' => false]);
        $firstValue = $attribute->values()->create(['name' => 'کوچک']);
        $secondValue = $attribute->values()->create(['name' => 'بزرگ']);
        app(ProductCatalogService::class)->syncAttributes($product, [['attribute_id' => $attribute->id, 'value_ids' => [$secondValue->id, $firstValue->id]]]);
        $user = User::factory()->create();
        $first = $user->addMedia(UploadedFile::fake()->image('portrait.jpg', 100, 200))->withCustomProperties(['alt_text' => 'تصویر عمودی واقعی'])->toMediaCollection('media_library', 'public');
        $second = $user->addMedia(UploadedFile::fake()->image('landscape.jpg', 200, 100))->toMediaCollection('media_library', 'public');
        ProductResource::syncFeaturedImage($product, $first->id);
        ProductResource::syncMediaLibraryCollection($product, 'gallery', [$first->id, $second->id]);
        $context = app(ProductTemplateContextBuilder::class)->build($product->fresh());
        $this->assertSame(['خانه', 'فروشگاه', 'ریشه', 'فرزند', $product->title], array_column($context['breadcrumbs'], 'label'));
        $this->assertSame(['name' => 'برند واقعی'], $context['brand']);
        $this->assertSame(['بزرگ', 'کوچک'], $context['globalAttributes'][0]['values']);
        $this->assertCount(2, $context['galleryPresentation']);
        $this->assertSame([$first->id, $second->id], array_column($context['galleryPresentation'], 'id'));
        $this->assertSame('تصویر عمودی واقعی', $context['galleryPresentation'][0]['alt']);
        $brand->update(['status' => 'inactive']);
        $attribute->update(['status' => 'inactive']);
        $root->update(['status' => 'inactive']);
        // A new builder request must not reuse request-local category metadata.
        $hidden = app(ProductTemplateContextBuilder::class)->build($product->fresh());
        $this->assertNull($hidden['brand']);
        $this->assertSame([], $hidden['globalAttributes']);
        $this->assertSame(['خانه', 'فروشگاه', $product->title], array_column($hidden['breadcrumbs'], 'label'));
        $this->assertNull($hidden['displayCategory']);
    }

    public function test_details_reuse_specifications_documents_and_keep_all_panels_in_initial_html(): void
    {
        $template = $this->standard();
        $blocks = $template->blocks;
        $blocks[2]['data']['content']['buying_guide'] = '<p>راهنمای واقعی خرید</p>';
        $template->update(['blocks' => $blocks]);
        $product = Product::factory()->published()->create(['content' => '<p>شرح واقعی محصول</p>']);
        $product->specifications()->create(['group_name' => 'ابعاد', 'label' => 'طول', 'value' => str_repeat('مقدار بلند ', 30), 'unit' => 'mm']);
        $product->documents()->create(['title' => 'فایل راهنما', 'external_url' => 'https://example.com/guide.pdf']);
        $attribute = ProductAttribute::create(['name' => 'جنس واقعی']);
        $value = $attribute->values()->create(['name' => 'مقدار واقعی']);
        app(ProductCatalogService::class)->syncAttributes($product, [['attribute_id' => $attribute->id, 'value_ids' => [$value->id]]]);
        $response = $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('شرح واقعی محصول')->assertSee('فایل راهنما')->assertSee('مقدار واقعی')->assertSee('راهنمای واقعی خرید');
        $this->assertSame(5, preg_match_all('/data-product-panel="/', $response->getContent()));
        $this->assertDoesNotMatchRegularExpression('/<section[^>]*data-product-panel[^>]*hidden/', $response->getContent());
        $context = app(ProductTemplateContextBuilder::class)->build($product->fresh());
        $html = view('partials.blocks.product_details', ['data' => [], 'context' => $context])->render();
        $this->assertStringContainsString('data-product-panel="specifications"', $html);
        $this->assertStringNotContainsString('data-product-panel="guide"', $html);
    }

    public function test_empty_rich_text_does_not_create_empty_tabs(): void
    {
        $product = Product::factory()->published()->create(['content' => '<p>&nbsp;<br> </p>']);
        $context = app(ProductTemplateContextBuilder::class)->build($product);
        $html = view('partials.blocks.product_details', ['data' => ['content' => ['buying_guide' => '<p>&nbsp;</p>']], 'context' => $context])->render();
        $this->assertStringNotContainsString('data-product-tabs', $html);
    }

    public function test_new_renderers_execute_no_queries_with_related_cards_and_freshness_enabled(): void
    {
        $template = $this->standard();
        app(SettingsService::class)->set('shop_price_freshness_enabled', true);
        app(SettingsService::class)->set('shop_price_show_verified_at', true);
        $actor = User::factory()->admin()->create();
        $product = Product::factory()->published()->create(['content' => '<p>شرح</p>']);
        $product = app(ProductPriceFreshness::class)->verify($product, $actor);
        $related = Product::factory()->published()->create();
        $product->relatedProducts()->attach($related, ['sort_order' => 1]);
        $context = app(ProductTemplateContextBuilder::class)->build($product);
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (['product_summary', 'product_details', 'product_related'] as $type) {
            $data = collect($template->blocks)->firstWhere('type', $type)['data'];
            view('partials.blocks.'.$type, compact('data', 'context'))->render();
        }
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_benefits_and_cta_render_only_configured_content_with_a_resolved_destination(): void
    {
        $template = $this->standard();
        $product = Product::factory()->published()->create();
        $blocks = $template->blocks;
        $blocks[1]['data']['content']['items'] = [['title' => 'مزیت تأییدشده مدیر', 'description' => 'توضیح واقعی مدیر']];
        $blocks[1]['data'] = app(FeatureGridDataNormalizer::class)->normalize($blocks[1]['data']);
        $blocks[4]['data']['content']['title'] = 'راهنمایی اختصاصی فروشگاه';
        $blocks[4]['data']['content']['primary_cta'] = ['label' => 'مشاهده فروشگاه', 'action' => ['type' => 'custom_url', 'value' => route('shop.index')]];
        $blocks[4]['data'] = app(CTADataNormalizer::class)->normalize($blocks[4]['data']);
        $template->update(['blocks' => $blocks]);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('مزیت تأییدشده مدیر')->assertSee('راهنمایی اختصاصی فروشگاه')->assertSee('مشاهده فروشگاه');
        $blocks[4]['data']['content']['primary_cta']['action'] = ['type' => 'form', 'reference_id' => 999999, 'display' => 'page'];
        $blocks[4]['data'] = app(CTADataNormalizer::class)->normalize($blocks[4]['data']);
        $template->update(['blocks' => $blocks]);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertDontSee('راهنمایی اختصاصی فروشگاه');
    }

    public function test_expired_price_uses_the_real_inquiry_form_and_hides_purchase_and_offers(): void
    {
        $this->standard();
        $this->travelTo(now()->startOfSecond());
        $settings = app(SettingsService::class);
        $settings->set('shop_price_freshness_enabled', true);
        $settings->set('shop_price_validity_days', 1);
        $form = Form::create(['name' => 'استعلام واقعی', 'slug' => 'standard-inquiry', 'status' => 'published', 'type' => 'normal', 'schema' => []]);
        $settings->set('shop_price_inquiry_form_id', $form->id);
        $product = app(ProductPriceFreshness::class)->verify(Product::factory()->published()->create(['price' => '150.00', 'sale_price' => '120.00']), User::factory()->admin()->create());
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('action="'.route('cart.add', $product).'"', false)->assertSee('۱۲۰ تومان')->assertSee('<del', false);
        $this->travel(2)->days();
        $response = $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('آخرین قیمت ثبت‌شده')->assertSee('درخواست استعلام قیمت')->assertSee('_price_product', false)->assertDontSee('action="'.route('cart.add', $product).'"', false);
        $this->assertStringNotContainsString('"offers"', $response->getContent());
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasErrors();
    }

    public function test_related_media_queries_do_not_grow_with_card_count(): void
    {
        Storage::fake('public');
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->published()->create(['product_category_id' => $category->id]);
        $items = Product::factory()->count(6)->published()->create(['product_category_id' => $category->id]);
        $media = User::factory()->create()->addMedia(UploadedFile::fake()->image('shared.jpg'))->toMediaCollection('media_library', 'public');
        foreach ($items as $item) {
            ProductResource::syncFeaturedImage($item, $media->id);
        }
        $counts = [];
        foreach ([2, 6] as $limit) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $context = app(ProductTemplateContextBuilder::class)->build($product->fresh(), $limit);
            $this->assertCount($limit, $context['relatedCards']);
            $counts[] = collect(DB::getQueryLog())->reject(fn ($query) => str_contains($query['query'], '"settings"'))->count();
            DB::disableQueryLog();
        }
        $this->assertSame($counts[0], $counts[1]);
    }

    public function test_preview_disables_mutating_controls_and_favorites_use_existing_session_route(): void
    {
        $template = $this->standard(false);
        $product = Product::factory()->published()->create();
        $this->post(route('shop.favorites.toggle', $product))->assertSessionHas('shop.favorite_product_ids', [$product->id]);
        $template->update(['status' => 'published']);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('aria-pressed="true"', false)->assertSee('حذف از علاقه‌مندی‌های این مرورگر');
        $this->actingAs(User::factory()->admin()->create());
        $template->update(['status' => 'draft']);
        $this->get(route('admin.preview.templates.show', $template).'?context_id='.$product->id)
            ->assertOk()->assertSee('noindex, nofollow')->assertSee('پیش‌نمایش مدیر')
            ->assertDontSee('action="'.route('cart.add', $product).'"', false)
            ->assertDontSee('action="'.route('shop.favorites.toggle', $product).'"', false)
            ->assertSessionHas('shop.favorite_product_ids', [$product->id]);
    }

    #[DataProvider('quantities')]
    public function test_standard_purchase_uses_existing_integer_quantity_contract(mixed $quantity, bool $valid): void
    {
        $this->standard();
        $product = Product::factory()->published()->create();
        $response = $this->post(route('cart.add', $product), ['quantity' => $quantity]);
        if ($valid) {
            $response->assertRedirect(route('cart.index'))->assertSessionHasNoErrors();
        } else {
            $response->assertSessionHasErrors('quantity');
        }
    }

    public static function quantities(): array
    {
        return [[1, true], [99, true], [0, false], [100, false], ['1.5', false], ['-2', false], ['x', false]];
    }

    public function test_sale_zero_freshness_and_stock_use_existing_purchase_rules_and_order_snapshot(): void
    {
        $this->standard();
        $product = Product::factory()->published()->create(['price' => '20.00', 'sale_price' => '0.00']);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('۰ تومان')->assertSee('<del', false);
        app(SettingsService::class)->set('shop_price_freshness_enabled', true);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertDontSee('action="'.route('cart.add', $product).'"', false)->assertSee('هنوز تأیید نشده');
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasErrors();
        app(SettingsService::class)->set('shop_price_freshness_enabled', false);
        $product->update(['sale_price' => '10.25']);
        Mail::fake();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->client()->create(), 'client');
        $this->post(route('cart.add', $product), ['quantity' => 2])->assertRedirect(route('cart.index'));
        $checkout = $this->get(route('checkout.create'))->assertOk();
        $this->post(route('checkout.store'), ['customer_name' => 'مشتری', 'customer_phone' => '09121234567', '_cart_review' => $checkout->viewData('cartReview'), '_checkout_intent' => $checkout->viewData('checkoutIntent')])->assertRedirect();
        $order = Order::sole();
        $this->assertSame('20.50', $order->total);
        $this->assertSame('10.25', $order->items()->sole()->unit_price);
        $product->update(['has_stock' => false]);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('ناموجود')->assertDontSee('action="'.route('cart.add', $product).'"', false);
    }

    public function test_related_order_and_legacy_industrial_presentation_remain_independent(): void
    {
        $standard = $this->standard();
        $product = Product::factory()->published()->create();
        $first = Product::factory()->published()->create(['title' => 'مرتبط اول']);
        $second = Product::factory()->published()->create(['title' => 'مرتبط دوم']);
        $draft = Product::factory()->create(['status' => 'draft']);
        $product->relatedProducts()->attach([$second->id => ['sort_order' => 2], $first->id => ['sort_order' => 1], $draft->id => ['sort_order' => 0]]);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSeeInOrder(['مرتبط اول', 'مرتبط دوم'])->assertDontSee($draft->title);
        $industrial = app(TemplateRecipeInstantiator::class)->createDraft('product-industrial-v1', ['conditions' => ['type' => 'specific_item', 'item_id' => $product->id]]);
        $industrial->update(['status' => 'published']);
        $before = $industrial->getRawOriginal('blocks');
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('product-header')->assertDontSee('data-product-summary', false)->assertDontSee('product-related-compact__grid', false);
        $this->assertSame($before, $industrial->fresh()->getRawOriginal('blocks'));
        $this->assertTrue($standard->fresh()->is_default);
    }
}
