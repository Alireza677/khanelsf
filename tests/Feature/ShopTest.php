<?php

namespace Tests\Feature;

use App\Contracts\PaymentGateway;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_index_loads_and_published_product_loads(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'Published Product',
            'slug' => 'published-product',
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('Published Product');

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('Published Product')
            ->assertSee('افزودن به سبد خرید');
    }

    public function test_draft_product_returns_404(): void
    {
        $product = Product::factory()->draft()->create([
            'slug' => 'draft-product',
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertNotFound();
    }

    public function test_products_can_be_added_to_and_filtered_by_favorites(): void
    {
        $favorite = Product::factory()->published()->create(['title' => 'Favorite Product']);
        $other = Product::factory()->published()->create(['title' => 'Other Product']);

        $this->post(route('shop.favorites.toggle', $favorite))
            ->assertRedirect();

        $this->assertSame([$favorite->id], session('shop.favorite_product_ids'));

        $this->get(route('shop.index', ['favorites' => 1]))
            ->assertOk()
            ->assertSee('Favorite Product')
            ->assertDontSee('Other Product');

        $this->post(route('shop.favorites.toggle', $favorite));

        $this->assertSame([], session('shop.favorite_product_ids'));
    }

    public function test_product_category_archive_loads(): void
    {
        $category = ProductCategory::factory()->create([
            'name' => 'Service Packages',
            'slug' => 'service-packages',
        ]);

        Product::factory()->published()->create([
            'product_category_id' => $category->id,
            'title' => 'Category Product',
        ]);

        $this->get(route('shop.category', $category->slug))
            ->assertOk()
            ->assertSee('Service Packages')
            ->assertSee('Category Product');
    }

    public function test_cart_add_update_and_remove(): void
    {
        $product = Product::factory()->published()->create([
            'price' => 100,
            'sale_price' => 80,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 2])
            ->assertRedirect(route('cart.index'));

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee($product->title)
            ->assertSee('160 تومان');

        $this->patch(route('cart.update'), [
            'quantities' => [$product->id => 3],
        ])->assertRedirect();

        $this->get(route('cart.index'))
            ->assertSee('240 تومان');

        $this->delete(route('cart.remove'), [
            'product_id' => $product->id,
        ])->assertRedirect();

        $this->get(route('cart.index'))
            ->assertSee('سبد خرید شما خالی است.');
    }

    public function test_checkout_page_requires_cart_and_loads_when_cart_has_items(): void
    {
        $this->get(route('checkout.create'))
            ->assertRedirect(route('cart.index'));

        $product = Product::factory()->published()->create([
            'title' => 'Checkout Product',
            'price' => 60,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1]);

        $this->get(route('checkout.create'))
            ->assertOk()
            ->assertSee('تسویه حساب')
            ->assertSee('60 تومان')
            ->assertSee('برای تایید و پیگیری سفارش الزامی است.');
    }

    public function test_checkout_creates_order_and_clears_cart(): void
    {
        Mail::fake();

        $product = Product::factory()->published()->create([
            'price' => 50,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 2]);

        $response = $this->post(route('checkout.store'), [
            'customer_name' => 'Jane Client',
            'customer_phone' => '+1 555 000 0000',
            'customer_email' => 'jane@example.com',
            'customer_address' => '123 Main Street',
            'notes' => 'Please call first.',
        ]);

        $order = Order::query()->first();

        $response->assertRedirect(route('checkout.thank-you', $order));

        $this->assertDatabaseHas('orders', [
            'customer_email' => 'jane@example.com',
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'manual',
            'payment_status' => 'unpaid',
        ]);

        $this->assertDatabaseHas('order_items', [
            'product_id' => $product->id,
            'quantity' => 2,
            'total' => 100,
        ]);

        $this->get(route('cart.index'))
            ->assertSee('سبد خرید شما خالی است.');
    }

    public function test_out_of_stock_product_cannot_be_added_to_cart(): void
    {
        $product = Product::factory()->published()->create([
            'has_stock' => false,
            'stock_status' => 'out_of_stock',
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1])
            ->assertNotFound();

        $this->get(route('cart.index'))
            ->assertSee('سبد خرید شما خالی است.');
    }

    public function test_checkout_validation_failure_keeps_cart(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'Validation Product',
            'price' => 40,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1]);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Jane Client',
            'customer_email' => 'not-an-email',
        ])->assertSessionHasErrors(['customer_phone', 'customer_email']);

        $this->get(route('cart.index'))
            ->assertOk()
            ->assertSee('Validation Product')
            ->assertSee('40 تومان');
    }

    public function test_order_status_helpers_work(): void
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-HELPERS',
            'customer_name' => 'Helper Customer',
            'customer_phone' => '+1 555 000 2222',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'manual',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ]);

        $this->assertTrue($order->isPending());
        $this->assertFalse($order->isPaid());
        $this->assertTrue($order->canBeCancelled());

        $order->markPaid();
        $order->refresh();

        $this->assertTrue($order->isPaid());
        $this->assertSame(Order::STATUS_PAID, $order->status);

        $order->markCompleted();
        $order->refresh();

        $this->assertSame(Order::STATUS_COMPLETED, $order->status);
        $this->assertFalse($order->canBeCancelled());
        $this->assertFalse($order->cancel());
    }

    public function test_payment_gateway_placeholder_is_bound(): void
    {
        $gateway = app(PaymentGateway::class);

        $this->assertSame('manual', $gateway->method());
        $this->assertSame(Order::PAYMENT_STATUS_UNPAID, $gateway->initialPaymentStatus());
        $this->assertNotEmpty($gateway->instructions());
    }

    public function test_thank_you_page_loads_with_order_details(): void
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-TEST-100',
            'customer_name' => 'Jane Client',
            'customer_phone' => '+1 555 000 0000',
            'customer_email' => 'jane@example.com',
            'customer_address' => '123 Main Street',
            'status' => 'pending',
            'subtotal' => 50,
            'total' => 50,
            'payment_method' => 'manual',
            'payment_status' => 'unpaid',
        ]);

        $order->items()->create([
            'product_title' => 'Order Item',
            'unit_price' => 25,
            'quantity' => 2,
            'total' => 50,
        ]);

        $this->get(route('checkout.thank-you', $order))
            ->assertOk()
            ->assertSee('ORD-TEST-100')
            ->assertSee('Jane Client')
            ->assertSee('Order Item')
            ->assertSee('پرداخت فعلا به‌صورت دستی انجام می‌شود.');
    }

    public function test_order_emails_do_not_break_checkout(): void
    {
        Mail::fake();

        Setting::query()->create([
            'key' => 'shop_order_admin_email',
            'value' => 'orders@example.com',
            'group' => 'shop',
            'type' => 'text',
        ]);

        $product = Product::factory()->published()->create([
            'price' => 75,
        ]);

        $this->post(route('cart.add', $product), ['quantity' => 1]);

        $this->post(route('checkout.store'), [
            'customer_name' => 'Email Customer',
            'customer_phone' => '+1 555 000 1111',
            'customer_email' => 'customer@example.com',
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'customer_email' => 'customer@example.com',
            'total' => 75,
        ]);
    }

    public function test_product_preview_is_auth_protected_and_noindexed(): void
    {
        $product = Product::factory()->draft()->create([
            'title' => 'Draft Preview Product',
        ]);

        $this->get(route('admin.preview.products.show', $product))
            ->assertRedirect();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.products.show', $product))
            ->assertOk()
            ->assertSee('Draft Preview Product')
            ->assertSee('noindex, nofollow');
    }

    public function test_shop_can_be_disabled(): void
    {
        Setting::query()->create([
            'key' => 'shop_enabled',
            'value' => '0',
            'group' => 'shop',
            'type' => 'boolean',
        ]);

        Product::factory()->published()->create();
        ProductCategory::factory()->create();

        $this->get(route('shop.index'))->assertNotFound();
    }
}
