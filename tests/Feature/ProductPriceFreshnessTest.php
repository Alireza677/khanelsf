<?php

namespace Tests\Feature;

use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Form;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductPriceHistory;
use App\Models\User;
use App\Services\CartService;
use App\Services\ProductAvailabilityService;
use App\Services\ProductPriceFreshness;
use App\Services\ProductPriceInquiry;
use App\Services\ProductTemplateContextBuilder;
use App\Services\SeoService;
use App\Services\SettingsService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ProductPriceFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->startOfSecond());
        $this->actor = User::factory()->admin()->create();
        Mail::fake();
        Http::preventStrayRequests();
    }

    private function settings(string $key, mixed $value): void
    {
        app(SettingsService::class)->set('shop_price_'.$key, $value, 'shop');
    }

    private function verified(array $attributes = []): Product
    {
        return app(ProductPriceFreshness::class)->verify(Product::factory()->published()->create([
            'price' => '123.45', 'sale_price' => null, ...$attributes,
        ]), $this->actor);
    }

    public function test_first_price_and_reconfirmation_have_separate_dates_and_exact_audit_values(): void
    {
        $product = Product::factory()->create(['price' => '123.45', 'sale_price' => '100.15']);
        $changed = $product->price_changed_at->toDateTimeString();
        $this->assertNull($product->price_verified_at);
        $this->travel(1)->day();
        $product = app(ProductPriceFreshness::class)->verify($product, $this->actor);
        $this->assertSame($changed, $product->price_changed_at->toDateTimeString());
        $this->assertSame('valid', app(ProductPriceFreshness::class)->status($product));
        $verified = $product->price_verified_at->toDateTimeString();
        $this->travel(1)->day();
        $product = app(ProductPriceFreshness::class)->verify($product, $this->actor);
        $this->assertNotSame($verified, $product->price_verified_at->toDateTimeString());
        $this->assertDatabaseCount('product_price_histories', 3);
        $event = $product->priceHistories()->first();
        $this->assertSame('price_verified', $event->event);
        $this->assertSame($this->actor->id, $event->user_id);
        $this->assertSame('100.15', $event->new_sale_price);
        $this->assertSame('123.45', $event->new_price);
    }

    public function test_nonfinancial_save_and_same_decimal_price_do_not_touch_dates_or_history(): void
    {
        $product = $this->verified(['price' => 100]);
        $before = $product->only('price_changed_at', 'price_verified_at');
        $this->travel(2)->days();
        $product->update(['title' => 'عنوان جدید', 'price' => '100.00', 'seo_title' => 'SEO']);
        $this->assertEquals($before, $product->fresh()->only('price_changed_at', 'price_verified_at'));
        $this->assertDatabaseCount('product_price_histories', 2);
    }

    public function test_price_and_sale_changes_invalidate_confirmation_even_when_effective_price_is_same(): void
    {
        $product = $this->verified(['price' => '100', 'sale_price' => '80']);
        $this->travel(1)->day();
        $product->update(['price' => '110']);
        $this->assertNull($product->fresh()->price_verified_at);
        $this->assertSame('unverified', app(ProductPriceFreshness::class)->status($product));
        $product = app(ProductPriceFreshness::class)->verify($product, $this->actor);
        $product->update(['sale_price' => null]);
        $this->assertNull($product->fresh()->price_verified_at);
        $this->assertSame('80.00', $product->priceHistories()->first()->old_sale_price);
    }

    public function test_statuses_use_exact_expiry_boundary_and_sql_filters_agree(): void
    {
        $this->settings('freshness_enabled', true);
        $this->settings('validity_days', 5);
        $this->settings('warning_days', 2);
        $product = $this->verified();
        $service = app(ProductPriceFreshness::class);
        $this->travel(3)->days();
        $this->assertSame('expiring_soon', $service->status($product));
        $this->assertSame([$product->id], $service->filter(Product::query(), 'expiring_soon')->pluck('id')->all());
        $this->travelTo($service->expiresAt($product)->subSecond());
        $this->assertTrue($service->allowsPurchase($product));
        $this->assertSame('expiring_soon', $service->status($product));
        $this->travel(1)->second();
        $this->assertSame('expired', $service->status($product));
        $this->assertSame([$product->id], $service->filter(Product::query(), 'expired')->pluck('id')->all());
    }

    public function test_product_category_store_priority_and_unlimited_requires_confirmation(): void
    {
        $this->settings('validity_days', 30);
        $category = ProductCategory::factory()->create(['price_validity_days' => 14]);
        $product = $this->verified(['product_category_id' => $category->id]);
        $service = app(ProductPriceFreshness::class);
        $this->assertSame(14, $service->days($product));
        $product->update(['price_validity_mode' => 'custom', 'price_validity_days' => 3]);
        $this->assertSame(3, $service->days($product));
        $product->update(['price_validity_mode' => 'unlimited']);
        $this->travel(100)->days();
        $this->assertSame('valid', $service->status($product));
        $this->assertNull($service->expiresAt($product));
        $this->assertSame([$product->id], $service->filter(Product::query(), 'valid')->pluck('id')->all());
        $product->update(['price' => 200]);
        $this->assertSame('unverified', $service->status($product));
        $category->update(['price_validity_days' => null]);
        $product->update(['price_validity_mode' => 'inherit']);
        $this->assertSame(30, $service->days($product->fresh()));
    }

    public function test_missing_and_unverified_prices_are_distinct_and_cannot_be_confirmed_as_zero(): void
    {
        $missing = Product::factory()->create(['price' => 0]);
        $unverified = Product::factory()->create(['price' => 10]);
        $service = app(ProductPriceFreshness::class);
        $this->assertSame('missing', $service->status($missing));
        $this->assertSame('unverified', $service->status($unverified));
        $this->assertSame([$missing->id], $service->filter(Product::query(), 'missing')->pluck('id')->all());
        $this->assertSame([$unverified->id], $service->filter(Product::query(), 'unverified')->pluck('id')->all());
        $this->expectException(ValidationException::class);
        $service->verify($missing, $this->actor);
    }

    public function test_stale_confirmation_cannot_approve_a_new_price(): void
    {
        $product = $this->verified();
        $stale = $product->fresh();
        $product->update(['price' => 999]);
        try {
            app(ProductPriceFreshness::class)->verify($stale, $this->actor);
            $this->fail('Stale confirmation accepted');
        } catch (ValidationException) {
            $this->assertNull($product->fresh()->price_verified_at);
            $this->assertDatabaseCount('product_price_histories', 3);
        }
    }

    public function test_rollback_leaves_no_price_change_confirmation_or_audit(): void
    {
        $product = $this->verified();
        try {
            DB::transaction(function () use ($product): void {
                $product->update(['price' => '333.33']);
                app(ProductPriceFreshness::class)->verify($product, $this->actor);
                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
        }
        $this->assertSame('123.45', $product->fresh()->price);
        $this->assertDatabaseCount('product_price_histories', 2);
    }

    public function test_cancelled_verification_save_cannot_create_a_successful_audit_event(): void
    {
        $product = Product::factory()->create(['price' => 10]);
        $dispatcher = Product::getEventDispatcher();
        Product::setEventDispatcher(clone $dispatcher);
        Product::saving(fn () => false);
        try {
            app(ProductPriceFreshness::class)->verify($product, $this->actor);
            $this->fail('Cancelled save was treated as verified');
        } catch (ValidationException) {
            $this->assertNull($product->fresh()->price_verified_at);
            $this->assertDatabaseCount('product_price_histories', 1);
        } finally {
            Product::setEventDispatcher($dispatcher);
        }
    }

    public function test_old_products_are_not_backfilled_and_disabled_control_preserves_purchase(): void
    {
        $product = Product::factory()->published()->create(['price' => 10]);
        DB::table('products')->where('id', $product->id)->update(['price_changed_at' => null]);
        $this->assertNull($product->fresh()->price_changed_at);
        $this->assertNull($product->price_verified_at);
        $this->post(route('cart.add', $product))->assertSessionHasNoErrors();
        $this->assertTrue($product->isPurchasable());
        $this->settings('freshness_enabled', true);
        $this->assertFalse($product->isPurchasable());
        $this->assertSame('in_stock', app(ProductAvailabilityService::class)->stockStatus($product));
    }

    public function test_publication_and_stock_remain_independent_purchase_requirements(): void
    {
        $product = $this->verified();
        foreach ([false, true] as $enabled) {
            $this->settings('freshness_enabled', $enabled);
            $product->update(['status' => 'draft']);
            $this->assertFalse(app(ProductAvailabilityService::class)->isPurchasable($product));
            $product->update(['status' => 'published', 'published_at' => now()->addDay()]);
            $this->assertFalse($product->isPurchasable());
            $product->update(['published_at' => null, 'stock_status' => 'out_of_stock']);
            $this->assertFalse($product->isPurchasable());
            $product->update(['stock_status' => 'in_stock', 'has_stock' => false]);
            $this->assertFalse($product->isPurchasable());
            $product->update(['has_stock' => true]);
            $this->assertTrue($product->isPurchasable());
        }
    }

    public function test_expired_price_is_visible_with_inquiry_and_without_offer_or_buy_button(): void
    {
        $this->settings('freshness_enabled', true);
        $this->settings('validity_days', 1);
        $this->settings('show_verified_at', true);
        app(SettingsService::class)->set('contact_phone', '02112345678');
        $product = $this->verified(['robots_index' => true]);
        $this->travel(1)->day();
        $this->get(route('shop.show', $product->slug))->assertOk()
            ->assertSee('آخرین قیمت ثبت‌شده')->assertSee('123.45 تومان')
            ->assertSee('آخرین تأیید قیمت')->assertSee('اعتبار این قیمت به پایان رسیده')
            ->assertSee('tel:02112345678', false)->assertDontSee('افزودن به سبد خرید');
        $seo = app(SeoService::class)->forProduct($product);
        $this->assertArrayNotHasKey('offers', $seo->schema);
        $this->assertSame('index, follow', $seo->robots);
        $this->assertSame('published', $product->fresh()->status);
        $this->get(route('sitemap'))->assertSee($product->slug);
        $this->post(route('cart.add', $product))->assertSessionHasErrors('cart');
    }

    public function test_expiry_in_cart_blocks_update_checkout_and_order_but_keeps_rows_removable(): void
    {
        $this->settings('freshness_enabled', true);
        $expired = $this->verified(['price_validity_mode' => 'custom', 'price_validity_days' => 1]);
        $valid = $this->verified(['price_validity_mode' => 'unlimited']);
        $this->post(route('cart.add', $expired))->assertSessionHasNoErrors();
        $this->post(route('cart.add', $valid))->assertSessionHasNoErrors();
        $this->travel(1)->day();
        $this->get(route('cart.index'))->assertOk()->assertSee('منقضی');
        $this->patch(route('cart.update'), ['quantities' => [$expired->id => 2]])->assertSessionHasErrors();
        $this->get(route('checkout.create'))->assertRedirect(route('cart.index'));
        $this->post(route('checkout.store'), ['customer_name' => 'مشتری آزمایشی', 'customer_phone' => '09121234567'])
            ->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertCount(2, app(CartService::class)->items());
        $this->delete(route('cart.remove'), ['product_id' => $expired->id])->assertRedirect();
        $this->assertSame([$valid->id], app(CartService::class)->items()->pluck('product_id')->all());
        $this->get(route('checkout.create'))->assertOk();
    }

    public function test_price_expiring_after_checkout_review_is_rejected_on_final_post(): void
    {
        $this->settings('freshness_enabled', true);
        $product = $this->verified(['price_validity_mode' => 'custom', 'price_validity_days' => 1]);
        $this->travelTo(app(ProductPriceFreshness::class)->expiresAt($product)->subSecond());
        $this->post(route('cart.add', $product));
        $page = $this->get(route('checkout.create'))->assertOk();
        $this->travel(1)->second();
        $this->post(route('checkout.store'), [
            'customer_name' => 'مشتری آزمایشی', 'customer_phone' => '09121234567',
            '_cart_review' => $page->viewData('cartReview'), '_checkout_intent' => $page->viewData('checkoutIntent'),
        ])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_signed_inquiry_reuses_form_and_stores_trusted_product_snapshot(): void
    {
        $form = Form::query()->create(['name' => 'استعلام', 'slug' => 'price-inquiry', 'status' => 'published', 'schema' => [], 'type' => 'normal']);
        $this->settings('inquiry_form_id', $form->id);
        $product = Product::factory()->published()->create(['sku' => 'TRUSTED']);
        $url = app(ProductPriceInquiry::class)->url($product);
        $this->get($url)->assertRedirect();
        $this->get(route('forms.show', ['slug' => $form->slug, '_form_instance' => 'price-product-'.$product->id]))->assertOk()->assertSee('TRUSTED');
        $this->post(route('forms.submit', $form->slug), [
            '_form_instance' => 'price-product-'.$product->id, '_price_inquiry' => ['sku' => 'FORGED'],
        ])->assertSessionHasNoErrors();
        $submission = $form->submissions()->sole();
        $this->assertSame('TRUSTED', $submission->payload['_price_inquiry']['sku']);
        $this->assertSame($product->id, $submission->payload['_price_inquiry']['product_id']);
        $this->get(str_replace('_price_product='.$product->id, '_price_product=999', $url))->assertForbidden();
    }

    public function test_unconfigured_inquiry_produces_no_dead_link_and_missing_price_is_not_zero(): void
    {
        $this->settings('freshness_enabled', true);
        $product = Product::factory()->published()->create(['price' => 0]);
        $this->assertNull(app(ProductPriceInquiry::class)->url($product));
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('قیمت نیازمند استعلام')
            ->assertDontSee('>0 تومان', false)->assertDontSee('افزودن به سبد خرید');
    }

    public function test_phone_fallback_reuses_action_normalization_and_hides_invalid_contact(): void
    {
        $product = Product::factory()->published()->create();
        app(SettingsService::class)->set('contact_phone', '۰۲۱ ۱۲۳۴۵۶۷۸');
        $this->assertSame('tel:02112345678', app(ProductPriceInquiry::class)->url($product));
        app(SettingsService::class)->set('contact_phone', '+');
        $this->assertNull(app(ProductPriceInquiry::class)->url($product));
    }

    public function test_filament_can_confirm_without_changing_price_and_filter_status(): void
    {
        $this->actingAs($this->actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $product = Product::factory()->published()->create(['price' => 10]);
        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->callAction('verifyPrice')->assertHasNoActionErrors();
        $this->assertNotNull($product->fresh()->price_verified_at);
        $this->assertSame('10.00', $product->fresh()->price);
        Livewire::test(ListProducts::class)->filterTable('price_freshness', 'valid')
            ->assertCanSeeTableRecords([$product]);
        Livewire::test(ListProducts::class)->filterTable('price_freshness', 'unverified')
            ->assertCanNotSeeTableRecords([$product]);
    }

    public function test_audit_survives_product_deletion_and_cannot_be_edited(): void
    {
        $product = $this->verified();
        $product->delete();
        $this->assertDatabaseCount('product_price_histories', 2);
        $this->expectException(\LogicException::class);
        ProductPriceHistory::query()->first()->update(['new_price' => 999]);
    }

    public function test_all_status_filters_and_expiry_sort_work_with_category_and_product_overrides(): void
    {
        $category = ProductCategory::factory()->create(['price_validity_days' => 1]);
        $expired = $this->verified(['product_category_id' => $category->id]);
        $soon = $this->verified(['price_validity_mode' => 'custom', 'price_validity_days' => 3]);
        $valid = $this->verified(['price_validity_mode' => 'custom', 'price_validity_days' => 10]);
        $unlimited = $this->verified(['price_validity_mode' => 'unlimited']);
        $unverified = Product::factory()->create(['price' => 100]);
        $missing = Product::factory()->create(['price' => 0]);
        $this->travel(1)->day();
        $service = app(ProductPriceFreshness::class);
        foreach ([
            'expired' => [$expired->id], 'expiring_soon' => [$soon->id],
            'valid' => [$valid->id, $unlimited->id], 'missing' => [$missing->id], 'unverified' => [$unverified->id],
        ] as $state => $ids) {
            $this->assertEqualsCanonicalizing($ids, $service->filter(Product::query(), $state)->pluck('id')->all());
        }
        $this->assertSame([$expired->id, $soon->id, $valid->id], Product::query()
            ->whereIn('id', [$expired->id, $soon->id, $valid->id])
            ->orderByRaw('('.$service->expiryExpression().') asc')->pluck('id')->all());

        DB::enableQueryLog();
        foreach (Product::query()->with('category')->get() as $product) {
            $service->status($product);
            $service->days($product);
        }
        $categoryQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "product_categories"'));
        DB::disableQueryLog();
        $this->assertCount(1, $categoryQueries);
    }

    public function test_pending_admin_confirmation_rejects_a_concurrent_price_change(): void
    {
        $this->actingAs($this->actor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $product = $this->verified();
        $page = Livewire::test(ListProducts::class)->mountTableAction('verifyPrice', $product);
        $product->update(['price' => 555]);
        $page->callMountedTableAction()->assertHasErrors(['price']);
        $this->assertNull($product->fresh()->price_verified_at);
    }

    public function test_price_inquiry_context_expiry_cannot_silently_create_an_unlinked_submission(): void
    {
        $form = Form::query()->create(['name' => 'استعلام', 'slug' => 'inquiry-expiry', 'status' => 'published', 'schema' => [], 'type' => 'normal']);
        $this->settings('inquiry_form_id', $form->id);
        $product = Product::factory()->published()->create();
        $this->get(app(ProductPriceInquiry::class)->url($product))->assertRedirect();
        $this->travel(31)->minutes();
        $this->post(route('forms.submit', $form->slug), ['_form_instance' => 'price-product-'.$product->id])->assertRedirect();
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_industrial_template_uses_the_same_expired_price_and_purchase_rules(): void
    {
        $this->settings('freshness_enabled', true);
        $this->settings('validity_days', 1);
        $product = $this->verified();
        $template = app(TemplateRecipeInstantiator::class)->createDraft('product-industrial-v1', [
            'slug' => 'freshness-industrial', 'is_default' => true,
        ]);
        $template->update(['status' => 'published']);
        $this->travel(1)->day();
        $this->get(route('shop.show', $product->slug))->assertOk()
            ->assertSee('آخرین قیمت ثبت‌شده')->assertSee('123.45 تومان')
            ->assertSee('اعتبار این قیمت به پایان رسیده')->assertDontSee('افزودن به سبد خرید');
        $product->update(['price' => 0]);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('قیمت نیازمند استعلام')
            ->assertDontSee('>0 تومان', false)->assertDontSee('افزودن به سبد خرید');
    }

    public function test_enabled_freshness_is_prepared_before_query_free_product_block_rendering(): void
    {
        $this->settings('freshness_enabled', true);
        $this->settings('validity_days', 1);
        app(SettingsService::class)->set('contact_phone', '02112345678');
        $product = $this->verified();
        $related = $this->verified();
        $product->relatedProducts()->attach($related);
        $this->travel(1)->day();
        $context = app(ProductTemplateContextBuilder::class)->build($product);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $html = view('partials.blocks.product_header', ['data' => [], 'context' => $context])->render()
            .view('partials.blocks.product_related', ['data' => [], 'context' => $context])->render();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertSame([], $queries);
        $this->assertStringContainsString('آخرین قیمت ثبت‌شده', $html);
        $this->assertStringContainsString('tel:02112345678', $html);
    }

    public function test_site_settings_persist_freshness_configuration_in_existing_settings(): void
    {
        $this->actingAs($this->actor);
        Livewire::test(ManageSiteSettings::class)
            ->set('data.site_name', 'فروشگاه آزمایشی')
            ->set('data.shop_enabled', true)
            ->set('data.shop_price_freshness_enabled', true)
            ->set('data.shop_price_validity_days', 7)
            ->set('data.shop_price_warning_days', 2)
            ->set('data.shop_price_show_verified_at', true)
            ->call('save')->assertHasNoFormErrors();
        $this->assertTrue(app(ProductPriceFreshness::class)->enabled());
        $this->assertSame('7', app(SettingsService::class)->get('shop_price_validity_days'));
        $this->assertSame(2, app(ProductPriceFreshness::class)->warningDays());
    }

    public function test_additive_migration_can_be_rolled_back_and_reapplied_without_changing_legacy_prices(): void
    {
        $product = Product::factory()->published()->create(['price' => '123.45', 'sale_price' => '100.10']);
        $migration = require database_path('migrations/2026_11_08_000000_add_product_price_freshness.php');
        $migration->down();
        $this->assertFalse(Schema::hasColumn('products', 'price_verified_at'));
        $migration->up();
        $this->assertSame('123.45', $product->fresh()->price);
        $this->assertSame('100.10', $product->fresh()->sale_price);
        $this->assertSame('published', $product->fresh()->status);
        $this->assertNull($product->fresh()->price_verified_at);
        $this->assertNull($product->fresh()->price_changed_at);
    }

    public function test_migration_resumes_after_history_table_creation_failure_without_resetting_existing_columns(): void
    {
        $product = $this->verified(['price_validity_mode' => 'custom', 'price_validity_days' => 7]);
        $before = $product->fresh()->getAttributes();
        Schema::drop('product_price_histories');
        $migration = require database_path('migrations/2026_11_08_000000_add_product_price_freshness.php');
        $migration->up();
        $this->assertTrue(Schema::hasTable('product_price_histories'));
        $this->assertSame($before, $product->fresh()->getAttributes());
        DB::table('product_price_histories')->insert([
            'product_id' => $product->id, 'new_price' => $product->price,
            'event' => 'price_changed', 'source' => 'other',
        ]);
        $this->assertNotNull(ProductPriceHistory::query()->sole()->occurred_at);
        $migration->up();
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertDatabaseCount('product_price_histories', 1);
    }
}
