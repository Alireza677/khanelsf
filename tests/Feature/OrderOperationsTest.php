<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\OrderCsvExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_printable_order_page(): void
    {
        $order = $this->createOrder();

        $this->get(route('admin.orders.print', $order))
            ->assertRedirect();
    }

    public function test_admin_can_access_printable_order_page(): void
    {
        $order = $this->createOrder();
        $order->items()->create([
            'product_title' => 'Printable Item',
            'product_sku' => 'PRINT-1',
            'unit_price' => 25,
            'quantity' => 2,
            'total' => 50,
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.print', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Printable Item')
            ->assertSee('پرداخت: Unpaid');
    }

    public function test_non_admin_cannot_access_order_operations(): void
    {
        $order = $this->createOrder();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.print', $order))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.orders.export'))
            ->assertForbidden();
    }

    public function test_csv_export_helper_formats_order_rows(): void
    {
        $order = $this->createOrder([
            'order_number' => 'ORD-CSV',
            'customer_name' => 'CSV Customer',
            'customer_phone' => '+1 555 000 3333',
            'customer_email' => 'csv@example.com',
            'total' => 123.45,
        ]);

        $exporter = app(OrderCsvExporter::class);

        $this->assertSame([
            'order_number',
            'customer_name',
            'customer_phone',
            'customer_email',
            'total',
            'status',
            'payment_status',
            'created_at',
        ], $exporter->headings());

        $this->assertSame('ORD-CSV', $exporter->row($order)[0]);
        $this->assertSame('CSV Customer', $exporter->row($order)[1]);
        $this->assertSame('123.45', $exporter->row($order)[4]);
    }

    public function test_admin_can_download_orders_csv(): void
    {
        $this->createOrder([
            'order_number' => 'ORD-DOWNLOAD',
        ]);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.export'));

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('order_number', $response->streamedContent());
        $this->assertStringContainsString('ORD-DOWNLOAD', $response->streamedContent());
    }

    public function test_order_status_helpers_still_work(): void
    {
        $order = $this->createOrder();

        $this->assertTrue($order->isPending());
        $this->assertTrue($order->markPaid());
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertTrue($order->markCompleted());
        $this->assertFalse($order->refresh()->canBeCancelled());
        $this->assertFalse($order->cancel());
    }

    private function createOrder(array $attributes = []): Order
    {
        return Order::query()->create([
            'order_number' => 'ORD-OPS-'.fake()->unique()->numberBetween(1000, 9999),
            'customer_name' => 'Operations Customer',
            'customer_phone' => '+1 555 000 4444',
            'customer_email' => 'ops@example.com',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 50,
            'total' => 50,
            'payment_method' => 'manual',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            ...$attributes,
        ]);
    }
}
