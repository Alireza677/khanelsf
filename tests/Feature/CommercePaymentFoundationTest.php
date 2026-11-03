<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderPaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CommercePaymentFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_completing_unpaid_order_preserves_financial_state(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->admin()->create());
        $this->assertTrue($order->markCompleted());
        $this->assertSame('completed', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_manual_confirmation_has_exact_audit_and_replay_is_noop(): void
    {
        $order = $this->order();
        $actor = User::factory()->admin()->create();
        $service = app(OrderPaymentService::class);
        $first = $service->confirmManual($order, $actor, 'Receipt checked', 'RECEIPT-1');
        $this->travel(1)->hours();
        $second = $service->confirmManual($order, User::factory()->admin()->create(), 'Different note', 'OTHER');
        $this->assertSame($first->id, $second->id);
        $this->assertSame('0.70', $first->amount);
        $this->assertSame('IRT', $first->currency);
        $this->assertSame('IRT', $first->amount_unit);
        $this->assertSame('manual_confirmed', $first->source);
        $this->assertSame('manual_confirmed', $first->verification_outcome);
        $this->assertNull($first->verified_at);
        $this->assertNull($first->authority);
        $this->assertNull($first->provider_reference);
        $this->assertNull($first->gateway_amount_unit);
        $this->assertSame($actor->id, $second->confirmed_by);
        $this->assertSame(['id' => $actor->id, 'name' => $actor->name], $second->actor_snapshot);
        $this->assertSame('Receipt checked', $second->confirmation_reason);
        $this->assertSame('RECEIPT-1', $second->manual_reference);
        $this->assertTrue($first->confirmed_at->eq($second->confirmed_at));
        $this->assertSame($order->id, $first->order->id);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('paid', $order->status);
        $this->assertDatabaseCount('payment_attempts', 1);
        $service->complete($order, $actor);
        $this->assertSame('completed', $order->refresh()->status);
        $this->assertTrue($order->isPaid());
        $this->assertSame($first->id, $order->paymentAttempts()->sole()->id);
    }

    public function test_guest_client_inactive_and_revoked_admin_cannot_confirm(): void
    {
        $order = $this->order();
        $revoked = User::factory()->admin()->create();
        DB::table('users')->where('id', $revoked->id)->update(['is_admin' => false]);
        foreach ([null, User::factory()->client()->create(), User::factory()->admin()->create(['status' => 'inactive']), $revoked] as $actor) {
            try {
                app(OrderPaymentService::class)->confirmManual($order, $actor, 'Proof');
                $this->fail('Unauthorized confirmation accepted');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertDatabaseCount('payment_attempts', 0);
        $this->assertFalse($order->refresh()->isPaid());
    }

    public function test_manual_confirmation_requires_reason_and_manual_source(): void
    {
        $actor = User::factory()->admin()->create();
        foreach ([[$this->order(), ' '], [$this->order(['payment_method' => 'zarinpal']), 'Bank proof']] as [$order, $reason]) {
            try {
                app(OrderPaymentService::class)->confirmManual($order, $actor, $reason);
                $this->fail('Invalid manual confirmation accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment', $exception->errors());
            }
        }
        $this->assertDatabaseCount('payment_attempts', 0);
    }

    public function test_cancel_keeps_settlement_and_completed_cannot_cancel(): void
    {
        $actor = User::factory()->admin()->create();
        $service = app(OrderPaymentService::class);
        $order = $this->order();
        $evidence = $service->confirmManual($order, $actor, 'Receipt');
        $this->assertTrue($service->cancel($order, $actor));
        $this->assertSame('cancelled', $order->refresh()->status);
        $this->assertTrue($order->isPaid());
        $this->assertSame($evidence->id, $order->paymentAttempts()->sole()->id);
        $this->expectException(ValidationException::class);
        $service->complete($order, $actor);
    }

    public function test_completed_unpaid_can_be_manually_confirmed_without_changing_fulfillment(): void
    {
        $order = $this->order();
        $actor = User::factory()->admin()->create();
        $service = app(OrderPaymentService::class);
        $service->complete($order, $actor);
        $service->confirmManual($order, $actor, 'Payment after delivery');
        $this->assertSame('completed', $order->refresh()->status);
        $this->assertTrue($order->isPaid());
    }

    #[DataProvider('protectedMutations')]
    public function test_order_model_rejects_direct_financial_mutation(array $change): void
    {
        $order = $this->order();
        $this->expectException(LogicException::class);
        $order->forceFill($change)->save();
    }

    public static function protectedMutations(): array
    {
        return [
            [['payment_status' => 'paid']], [['status' => 'paid']], [['status' => 'completed']],
            [['total' => '9.00']], [['subtotal' => '9.00']], [['payment_method' => 'other']],
        ];
    }

    public function test_new_paid_order_cannot_bypass_settlement(): void
    {
        $this->expectException(LogicException::class);
        $this->order(['payment_status' => 'paid']);
    }

    public function test_legacy_paid_data_remains_readable_but_not_recharged_or_verified(): void
    {
        $order = $this->order();
        // Historical data fixture, not a new financial operation.
        DB::table('orders')->where('id', $order->id)->update(['status' => 'paid', 'payment_status' => 'paid']);
        $this->assertTrue($order->refresh()->isPaid());
        $this->assertSame('پرداخت قدیمی؛ منشأ تأیید نامشخص', $order->paymentProvenanceLabel());
        $this->assertDatabaseCount('payment_attempts', 0);
        app(OrderPaymentService::class)->complete($order, User::factory()->admin()->create());
        $this->assertSame('completed', $order->refresh()->status);
        $this->expectException(ValidationException::class);
        app(OrderPaymentService::class)->prepare($order, str_repeat('a', 32));
    }

    public function test_preparation_is_idempotent_and_failed_attempt_requires_a_new_intent(): void
    {
        $order = $this->order(['payment_method' => 'zarinpal']);
        $service = app(OrderPaymentService::class);
        $attempt = $service->prepare($order, str_repeat('a', 32));
        $this->assertSame($attempt->id, $service->prepare($order, str_repeat('a', 32))->id);
        $this->assertSame('0.70', $attempt->amount);
        $this->assertNull($attempt->gateway_amount);
        $this->assertSame('pending', $attempt->status);
        $service->recordUnsettled($attempt, 'failed', 'initiation_unavailable');
        $this->assertSame($attempt->id, $service->prepare($order, str_repeat('a', 32))->id);
        $next = $service->prepare($order, str_repeat('b', 32));
        $this->assertNotSame($attempt->id, $next->id);
        $this->assertSame('unpaid', $order->refresh()->payment_status);
        $this->assertDatabaseCount('payment_attempts', 2);
    }

    public function test_uncertain_attempt_blocks_retry_cancel_and_manual_settlement(): void
    {
        $order = $this->order();
        $service = app(OrderPaymentService::class);
        $attempt = $service->prepare($order, str_repeat('a', 32));
        $service->recordUnsettled($attempt, 'uncertain', 'transport_unknown');
        foreach ([fn () => $service->prepare($order, str_repeat('b', 32)),
            fn () => $service->cancel($order, User::factory()->admin()->create()),
            fn () => $service->confirmManual($order, User::factory()->admin()->create(), 'Proof'),
            fn () => $service->recordUnsettled($attempt, 'failed', 'initiation_unavailable')] as $operation) {
            try {
                $operation();
                $this->fail('Uncertain attempt was bypassed');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment', $exception->errors());
            }
        }
        $this->assertSame('unpaid', $order->refresh()->payment_status);
        $this->assertSame('uncertain', $attempt->refresh()->status);
        $this->assertSame(['code' => 'transport_unknown'], $attempt->diagnostics);
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_and_zero_snapshot_amounts_are_not_settled(mixed $amount): void
    {
        $order = $this->order();
        DB::table('orders')->where('id', $order->id)->update(['total' => $amount]);
        try {
            app(OrderPaymentService::class)->confirmManual($order, User::factory()->admin()->create(), 'Proof');
            $this->fail('Invalid amount accepted');
        } catch (\InvalidArgumentException|ValidationException $exception) {
            $this->assertDatabaseCount('payment_attempts', 0);
            $this->assertFalse($order->refresh()->isPaid());
        }
    }

    public static function invalidAmounts(): array
    {
        return [['0.00'], ['-1.00'], ['1.234'], ['10000000000.00'], ['broken']];
    }

    public function test_paid_and_cancelled_orders_cannot_prepare_new_attempts(): void
    {
        $actor = User::factory()->admin()->create();
        $service = app(OrderPaymentService::class);
        $paid = $this->order();
        $service->confirmManual($paid, $actor, 'Proof');
        $cancelled = $this->order();
        $service->cancel($cancelled, $actor);
        foreach ([$paid, $cancelled] as $order) {
            try {
                $service->prepare($order, str_repeat('c', 32));
                $this->fail('Invalid new attempt accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment', $exception->errors());
            }
        }
    }

    #[DataProvider('uniqueFields')]
    public function test_database_rejects_duplicate_financial_identifiers(string $field): void
    {
        $service = app(OrderPaymentService::class);
        $first = $service->prepare($this->order(), str_repeat('a', 32));
        $second = $service->prepare($this->order(), str_repeat('b', 32));
        $value = in_array($field, ['active_order_id', 'settlement_order_id'], true) ? $first->order_id : 'unique-fixture';
        DB::table('payment_attempts')->where('id', $first->id)->update([$field => $value]);
        $this->expectException(QueryException::class);
        DB::table('payment_attempts')->where('id', $second->id)->update([$field => $value]);
    }

    public static function uniqueFields(): array
    {
        return [['authority'], ['provider_reference'], ['initiation_key'], ['active_order_id'], ['settlement_order_id']];
    }

    public function test_attempt_snapshots_and_evidence_cannot_be_modified_or_deleted(): void
    {
        $attempt = app(OrderPaymentService::class)->prepare($this->order(), str_repeat('a', 32));
        foreach ([fn () => $attempt->forceFill(['amount' => '2.00'])->save(), fn () => $attempt->delete()] as $operation) {
            try {
                $operation();
                $this->fail('Attempt changed outside service');
            } catch (LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        $this->assertSame('0.70', $attempt->refresh()->amount);
    }

    public function test_gateway_success_and_network_initiation_are_unavailable(): void
    {
        $service = app(OrderPaymentService::class);
        $attempt = $service->prepare($this->order(['payment_method' => 'zarinpal']), str_repeat('a', 32));
        foreach ([fn () => $service->initiateGateway($attempt), fn () => $service->settleGateway($attempt, ['verified' => true])] as $operation) {
            try {
                $operation();
                $this->fail('P2 exposed a gateway success operation');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('P3', $exception->getMessage());
            }
        }
        $this->assertSame('unpaid', $attempt->order->payment_status);
        Http::assertNothingSent();
    }

    public function test_admin_form_ignores_forged_financial_state_and_exposes_history(): void
    {
        $order = $this->order();
        $order->items()->create(['product_title' => 'Original snapshot', 'quantity' => 1, 'unit_price' => '0.70', 'total' => '0.70']);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->set('data.payment_status', 'paid')->set('data.status', 'completed')
            ->set('data.total', '9000.00')->set('data.payment_method', 'zarinpal')
            ->set('data.items', [])
            ->set('data.admin_note', 'Private note')->call('save')->assertHasNoFormErrors();
        $this->assertSame('unpaid', $order->refresh()->payment_status);
        $this->assertSame('pending', $order->status);
        $this->assertSame('0.70', $order->total);
        $this->assertSame('manual', $order->payment_method);
        $this->assertSame('Private note', $order->admin_note);
        $this->assertDatabaseCount('order_items', 1);
        Livewire::test(ListOrders::class)->callTableAction('markPaid', $order, ['reason' => 'Checked receipt', 'reference' => 'R1'])
            ->assertHasNoTableActionErrors();
        $this->assertTrue($order->refresh()->isPaid());
        Livewire::test(EditOrder::class, ['record' => $order->id])->assertSee('تأیید دستی مدیر');
        Livewire::test(EditOrder::class, ['record' => $order->id])
            ->set('data.paymentAttempts', [])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertSame('Checked receipt', $order->paymentAttempts()->sole()->confirmation_reason);
    }

    public function test_order_policy_is_admin_only(): void
    {
        $order = $this->order();
        $client = User::factory()->client()->create();
        $this->assertFalse(Gate::forUser($client)->allows('update', $order));
        $this->assertFalse(Gate::forUser($client)->allows('viewAny', Order::class));
        $this->assertTrue(Gate::forUser(User::factory()->admin()->create())->allows('update', $order));
        $this->assertFalse(Gate::forUser(User::factory()->admin()->create(['status' => 'inactive']))->allows('update', $order));
    }

    public function test_an_initiation_key_cannot_be_bound_to_another_order(): void
    {
        $service = app(OrderPaymentService::class);
        $first = $service->prepare($this->order(), str_repeat('a', 32));
        $this->assertNotNull($first->order_id);
        $this->expectException(ValidationException::class);
        $service->prepare($this->order(), str_repeat('a', 32));
    }

    public function test_cancelled_attempt_never_marks_paid_and_cannot_be_reopened(): void
    {
        $service = app(OrderPaymentService::class);
        $order = $this->order();
        $attempt = $service->prepare($order, str_repeat('a', 32));
        $service->recordUnsettled($attempt, 'cancelled', 'cancelled_before_send');
        $this->assertSame('cancelled', $attempt->refresh()->status);
        $this->assertNull($attempt->active_order_id);
        $this->assertSame('unpaid', $order->refresh()->payment_status);
        $this->assertSame('cancelled', $service->prepare($order, str_repeat('a', 32))->status);
        $this->assertSame('pending', $service->prepare($order, str_repeat('b', 32))->status);
    }

    public function test_inconsistent_legacy_paid_marker_cannot_be_erased_then_recharged(): void
    {
        $order = $this->order();
        DB::table('orders')->where('id', $order->id)->update(['status' => 'paid']);
        $service = app(OrderPaymentService::class);
        try {
            $service->complete($order, User::factory()->admin()->create());
            $this->fail('Inconsistent legacy marker was erased');
        } catch (ValidationException $exception) {
            $this->assertSame('paid', $order->refresh()->status);
        }
        $this->expectException(ValidationException::class);
        $service->prepare($order, str_repeat('c', 32));
    }

    public function test_actor_snapshot_survives_administrator_deletion(): void
    {
        $actor = User::factory()->admin()->create();
        $id = $actor->id;
        $attempt = app(OrderPaymentService::class)->confirmManual($this->order(), $actor, 'Receipt');
        $actor->delete();
        $this->assertNull($attempt->refresh()->confirmed_by);
        $this->assertSame($id, $attempt->actor_snapshot['id']);
        $this->assertNotNull($attempt->confirmed_at);
    }

    private function order(array $attributes = []): Order
    {
        return Order::query()->create([
            'order_number' => 'P2-'.fake()->uuid(), 'customer_name' => 'P2 customer', 'customer_phone' => '09120000000',
            'status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'manual',
            'subtotal' => '0.70', 'total' => '0.70', ...$attributes,
        ]);
    }
}
