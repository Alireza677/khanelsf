<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductBrand;
use App\Models\ProductTag;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProductCatalogCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_relations_do_not_change_purchase_identity_pricing_order_snapshot_or_preview(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->client()->create(), 'client');
        $brand = ProductBrand::create(['name' => 'Brand']);
        $tag = ProductTag::create(['name' => 'Tag']);
        $attribute = ProductAttribute::create(['name' => 'Material']);
        $value = $attribute->values()->create(['name' => 'Steel']);
        $product = Product::factory()->published()->create(['title' => 'Catalog purchase', 'price' => '20.00', 'sale_price' => '10.25', 'product_brand_id' => $brand->id]);
        $product->tags()->sync([$tag->id]);
        app(ProductCatalogService::class)->syncAttributes($product, [['attribute_id' => $attribute->id, 'value_ids' => [$value->id]]]);
        $this->get(route('shop.show', $product->slug))->assertOk()->assertSee('Catalog purchase');
        $this->post(route('cart.add', $product), ['quantity' => 2])->assertRedirect(route('cart.index'));
        $checkout = $this->get(route('checkout.create'))->assertOk();
        $this->assertSame('20.50', $checkout->viewData('subtotal'));
        $this->post(route('checkout.store'), [
            'customer_name' => 'Catalog customer', 'customer_phone' => '09121234567',
            '_cart_review' => $checkout->viewData('cartReview'), '_checkout_intent' => $checkout->viewData('checkoutIntent'),
        ])->assertRedirect();
        $order = Order::sole();
        $this->assertSame('20.50', $order->total);
        $this->assertSame('unpaid', $order->payment_status);
        $item = $order->items()->sole();
        $this->assertSame($product->id, $item->product_id);
        $this->assertSame('10.25', $item->unit_price);
        $product->update(['title' => 'Changed title', 'price' => '30.00', 'sale_price' => null]);
        $value->update(['status' => 'inactive']);
        $this->assertSame('Catalog purchase', $item->fresh()->product_title);
        $this->assertSame('20.50', $order->fresh()->total);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.preview.products.show', $product))->assertOk()->assertSee('noindex, nofollow');
    }
}
