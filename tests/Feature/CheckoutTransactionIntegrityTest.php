<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\ProductPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutTransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->client()->create(), 'client');

        Mail::fake();
        Http::preventStrayRequests();
    }

    #[DataProvider('invalidProductChanges')]
    public function test_product_is_revalidated_before_any_order_is_written(array $change): void
    {
        $product = Product::factory()->published()->create(['price' => '10.25']);
        $this->post(route('cart.add', $product), ['quantity' => 2])->assertRedirect();
        if ($change === []) {
            $product->delete();
        } else {
            $product->update($change);
        }

        $this->submit()->assertRedirect(route('checkout.create'))->assertSessionHasErrors('cart');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertCount(1, session('shop_cart'));
        $this->get(route('checkout.create'))->assertRedirect(route('cart.index'));
        $this->get(route('cart.index'))->assertOk()->assertSee('قابل خرید نیستند');
        $this->delete(route('cart.remove'), ['product_id' => $product->id])->assertRedirect();
        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    public static function invalidProductChanges(): array
    {
        return [
            'deleted' => [[]], 'draft' => [['status' => 'draft']],
            'scheduled' => [['published_at' => '2099-01-01']],
            'purchase disabled' => [['has_stock' => false]], 'out of stock' => [['stock_status' => 'out_of_stock']],
        ];
    }

    #[DataProvider('changedPrices')]
    public function test_changed_price_requires_a_recoverable_explicit_review(array $change, string $expected): void
    {
        $product = Product::factory()->published()->create(['price' => '20.25', 'sale_price' => null]);
        $this->post(route('cart.add', $product), ['quantity' => 2]);
        $oldToken = $this->get(route('checkout.create'))->assertOk()->viewData('cartReview');
        $product->update($change);

        $this->submit(['_cart_review' => $oldToken])
            ->assertRedirect(route('checkout.create'))->assertSessionHasErrors('cart')
            ->assertSessionHasInput('customer_name', 'Integrity customer')
            ->assertSessionHasInput('notes', 'Keep entered information');
        $this->assertDatabaseCount('orders', 0);
        $review = $this->get(route('checkout.create'))->assertOk()->assertSee('تغییر کرده است')
            ->assertSee('value="Integrity customer"', false);
        $this->assertSame($expected, $review->viewData('subtotal'));
        // Refreshing another tab must not make the original reviewed form acceptable.
        $this->submit(['_cart_review' => $oldToken])->assertSessionHasErrors('cart');
        $this->submit(['prices_accepted' => true])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);

        $newToken = $this->get(route('checkout.create'))->viewData('cartReview');
        $this->submit(['_cart_review' => $newToken])->assertRedirect();
        $order = Order::query()->sole();
        $this->assertSame($expected, $order->total);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertTrue(app(CartService::class)->isEmpty());
    }

    public static function changedPrices(): array
    {
        return ['regular' => [['price' => '22.35'], '44.70'], 'sale starts' => [['sale_price' => '19.10'], '38.20']];
    }

    public function test_price_is_rechecked_on_every_resubmission_and_on_legacy_direct_posts(): void
    {
        $product = Product::factory()->published()->create(['price' => '10.00']);
        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $product->update(['price' => '11.00']);
        $this->submit()->assertSessionHasErrors('cart');
        $token = $this->get(route('checkout.create'))->viewData('cartReview');
        $product->update(['price' => '12.00']);
        $this->submit(['_cart_review' => $token])->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertCount(1, session('shop_cart'));
    }

    public function test_exact_totals_and_snapshots_use_database_metadata_and_ignore_request_injection(): void
    {
        $first = Product::factory()->published()->create(['title' => 'First original', 'sku' => 'SKU-OLD', 'price' => '0.10']);
        $second = Product::factory()->published()->create(['price' => '0.20']);
        $this->post(route('cart.add', $first), ['quantity' => 3]);
        $this->post(route('cart.add', $second), ['quantity' => 2]);
        $first->update(['title' => 'Database title', 'sku' => 'SKU-DB']);
        $rows = session('shop_cart');
        $rows[0]['title'] = 'Forged snapshot title';
        $rows[0]['sku'] = 'FORGED';
        $this->withSession(['shop_cart' => $rows]);
        $this->submit([
            'price' => '0.01', 'total' => '-100', 'currency' => 'USD',
            'product_title' => 'Injected', 'product_id' => 999999, 'user_id' => 999999,
        ])->assertRedirect();

        $order = Order::query()->with('items')->sole();
        $this->assertSame('0.70', $order->total);
        $this->assertSame('0.70', $order->subtotal);
        $this->assertSame(auth('client')->id(), $order->user_id);
        $item = $order->items->firstWhere('product_id', $first->id);
        $this->assertSame('Database title', $item->product_title);
        $this->assertSame('SKU-DB', $item->product_sku);
        $this->assertSame('0.10', $item->unit_price);
        $this->assertSame('0.30', $item->total);
        $sum = $order->items->reduce(fn (string $sum, $item): string => bcadd($sum, $item->total, 2), '0.00');
        $this->assertSame($sum, $order->total);
        $first->update(['title' => 'Later title', 'price' => '8.00']);
        $first->delete();
        $this->assertSame('Database title', $item->fresh()->product_title);
        $this->assertSame('0.30', $item->fresh()->total);
        $this->assertNull($item->fresh()->product_id);
    }

    public function test_discount_policy_is_shared_and_legacy_higher_sale_uses_regular_price(): void
    {
        $product = Product::factory()->published()->create(['price' => '10.25', 'sale_price' => '20.00']);
        $this->assertSame(10.25, $product->currentPrice());
        $this->assertFalse($product->hasSalePrice());
        $this->assertSame(10.25, app(ProductPricingService::class)->effectivePrice($product));
        $this->post(route('cart.add', $product), ['quantity' => 3]);
        $this->submit()->assertRedirect();
        $this->assertSame('30.75', Order::query()->sole()->total);
    }

    public function test_sale_changes_that_do_not_change_effective_amount_do_not_require_an_extra_review(): void
    {
        $product = Product::factory()->published()->create(['price' => '10.00', 'sale_price' => '15.00']);
        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $token = $this->get(route('checkout.create'))->viewData('cartReview');
        $product->update(['sale_price' => '20.00']);
        $this->submit(['_cart_review' => $token])->assertRedirect();
        $this->assertSame('10.00', Order::query()->sole()->total);
    }

    #[DataProvider('invalidMoney')]
    public function test_malformed_or_unrepresentable_money_blocks_checkout(string $value): void
    {
        $product = Product::factory()->published()->create(['price' => '10.00']);
        $this->post(route('cart.add', $product), ['quantity' => 1]);
        // SQLite fixtures deliberately model invalid legacy/imported values.
        DB::table('products')->where('id', $product->id)->update(['price' => $value]);

        $this->submit()->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('checkout.create'))->assertRedirect(route('cart.index'));
        $this->get(route('cart.index'))->assertOk();
    }

    public static function invalidMoney(): array
    {
        return ['text' => ['bad-money'], 'negative' => ['-0.01'], 'extra precision' => ['1.234'], 'too large' => ['10000000000.00']];
    }

    #[DataProvider('invalidQuantities')]
    public function test_invalid_update_and_checkout_quantities_are_rejected(mixed $quantity): void
    {
        $product = Product::factory()->published()->create(['price' => '10.00']);
        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $this->patch(route('cart.update'), ['quantities' => [$product->id => $quantity]])
            ->assertSessionHasErrors('quantities.'.$product->id);
        $this->assertSame(1, app(CartService::class)->items()->sole()['quantity']);
        $rows = session('shop_cart');
        $rows[0]['quantity'] = $quantity;
        $this->withSession(['shop_cart' => $rows]);
        $this->submit()->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('cart.index'))->assertOk();
        $this->delete(route('cart.remove'), ['product_id' => $product->id])->assertRedirect();
    }

    public static function invalidQuantities(): array
    {
        return ['zero' => [0], 'negative' => [-1], 'decimal' => ['1.5'], 'too many' => [100],
            'overflow' => ['99999999999999999999999999'], 'text' => ['abc'], 'array' => [[1]], 'float' => [1.5], 'integral float' => [1.0]];
    }

    public function test_cumulative_addition_is_limited_atomically_and_update_uses_the_same_limit(): void
    {
        $product = Product::factory()->published()->create(['price' => '0.10']);
        $this->post(route('cart.add', $product), ['quantity' => 60])->assertRedirect();
        $this->post(route('cart.add', $product), ['quantity' => 40])->assertSessionHasErrors('quantity');
        $this->assertSame(60, app(CartService::class)->count());
        $this->post(route('cart.add', $product), ['quantity' => 39])->assertSessionHasNoErrors();
        $this->assertSame(99, app(CartService::class)->count());
        $this->patch(route('cart.update'), ['quantities' => [$product->id => 99]])->assertSessionHasNoErrors();
        $this->submit()->assertRedirect();
        $this->assertSame('9.90', Order::query()->sole()->total);
    }

    public function test_line_and_combined_order_overflow_are_rejected_without_partial_orders(): void
    {
        $first = Product::factory()->published()->create(['price' => '5000000000.00']);
        $second = Product::factory()->published()->create(['price' => '5000000000.00']);
        $this->post(route('cart.add', $first), ['quantity' => 2])->assertSessionHasErrors('cart');
        $this->assertTrue(app(CartService::class)->isEmpty());
        $this->post(route('cart.add', $first), ['quantity' => 1]);
        $this->patch(route('cart.update'), ['quantities' => [$first->id => 2]])->assertSessionHasErrors('quantities.'.$first->id);
        $this->post(route('cart.add', $second), ['quantity' => 1]);
        $this->submit()->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertCount(2, session('shop_cart'));
    }

    public function test_decimal_capacity_boundary_is_preserved_and_formatted_without_rounding(): void
    {
        $product = Product::factory()->published()->create(['price' => '9999999999.99']);
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertRedirect();
        $review = $this->get(route('checkout.create'))->assertOk()->assertSee('9,999,999,999.99 تومان');
        $this->submit(['_cart_review' => $review->viewData('cartReview')])->assertRedirect();
        $this->assertSame('9999999999.99', Order::query()->sole()->total);
    }

    public function test_precise_price_parser_does_not_round_or_accept_exponential_values(): void
    {
        $pricing = app(ProductPricingService::class);
        $this->assertSame('0.30', $pricing->lineTotal('0.10', 3));
        $this->assertSame('0.70', $pricing->addAmounts('0.30', '0.40'));
        $this->assertSame('1,234.56', $pricing->formatAmount('1234.56'));
        foreach (['1e2', '1.001', '-1', 'NaN', '10000000000', '', 0.30000000000000004, 1.000000000000001] as $value) {
            try {
                $pricing->amount($value);
                $this->fail('Invalid monetary input was accepted: '.$value);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_forged_session_prices_cannot_override_authoritative_amounts(): void
    {
        $product = Product::factory()->published()->create(['price' => '10.25']);
        $this->post(route('cart.add', $product), ['quantity' => 1]);
        $rows = session('shop_cart');
        $rows[0]['unit_price'] = '0.01';
        $this->withSession(['shop_cart' => $rows]);
        $this->submit()->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $review = $this->get(route('checkout.create'))->assertOk()->assertSee('10.25 تومان');
        $this->submit(['_cart_review' => $review->viewData('cartReview')])->assertRedirect();
        $this->assertSame('10.25', Order::query()->sole()->total);
    }

    public function test_zero_sale_is_valid_and_invalid_sale_precision_is_not_silently_rounded(): void
    {
        $product = Product::factory()->published()->create(['price' => '10.00', 'sale_price' => '0.00']);
        $this->post(route('cart.add', $product), ['quantity' => 1])->assertSessionHasNoErrors();
        DB::table('products')->where('id', $product->id)->update(['sale_price' => '0.001']);
        $this->submit()->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        DB::table('products')->where('id', $product->id)->update(['sale_price' => '0.00']);
        $token = $this->get(route('checkout.create'))->assertOk()->viewData('cartReview');
        $this->submit(['_cart_review' => $token])->assertRedirect();
        $this->assertSame('0.00', Order::query()->sole()->total);
        $this->assertSame('unpaid', Order::query()->sole()->payment_status);
    }

    public function test_product_reads_are_inside_the_same_transaction_as_order_persistence(): void
    {
        $first = Product::factory()->published()->create(['price' => '1.25']);
        $second = Product::factory()->published()->create(['price' => '2.50']);
        $this->post(route('cart.add', $first), ['quantity' => 1]);
        $this->post(route('cart.add', $second), ['quantity' => 1]);
        $initialLevel = DB::connection()->transactionLevel();
        $readLevels = [];
        DB::listen(function ($query) use (&$readLevels): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from "products"')) {
                $readLevels[] = DB::connection()->transactionLevel();
            }
        });
        $this->submit()->assertRedirect();

        $this->assertNotEmpty($readLevels);
        $this->assertGreaterThan($initialLevel, min($readLevels));
        $this->assertSame('3.75', Order::query()->sole()->total);
    }

    private function submit(array $overrides = [])
    {
        return $this->post(route('checkout.store'), array_replace([
            'customer_name' => 'Integrity customer', 'customer_phone' => '09120000000',
            'customer_email' => 'integrity@example.com', 'notes' => 'Keep entered information',
        ], $overrides));
    }
}
