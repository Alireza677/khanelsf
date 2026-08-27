<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ClientProjectCycleResolver;
use App\Services\CustomerMembershipManager;
use App\Services\DraftInvoiceItems;
use App\Services\InvoiceLifecycle;
use App\Services\InvoiceTotalsCalculator;
use App\Services\MonthlyInvoiceGenerator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class ClientInvoiceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_generator_aggregates_customer_projects_by_activity_date_and_snapshots_values(): void
    {
        $customer = Customer::factory()->create();
        $first = ClientProject::factory()->for($customer)->create(['title' => 'پروژه اول']);
        $second = ClientProject::factory()->for($customer)->create(['title' => 'پروژه دوم']);
        $this->activity($first, ['activity_date' => '2026-08-02', 'total_amount' => '100.25', 'service_name_snapshot' => 'خدمت تاریخی']);
        $this->activity($second, ['activity_date' => '2026-08-20', 'total_amount' => '200.75']);
        $this->activity($first, ['activity_date' => '2026-09-01', 'total_amount' => '999']);
        $this->activity($first, ['activity_date' => '2026-08-10', 'total_amount' => '999', 'status' => ClientProjectActivity::STATUS_CANCELLED]);
        $this->activity($first, ['activity_date' => '2026-08-11', 'total_amount' => null]);

        $invoice = $this->generate($customer);

        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertCount(2, $invoice->items);
        $this->assertSame('301.00', $invoice->subtotal);
        $this->assertSame('301.00', $invoice->total_amount);
        $this->assertSame('خدمت تاریخی', $invoice->items->first()->service_name_snapshot);
        $this->assertSame('پروژه اول', $invoice->items->first()->project_title_snapshot);
        $this->assertMatchesRegularExpression('/^INV-1405-\d{6}$/', $invoice->invoice_number);
    }

    public function test_totals_are_decimal_safe_and_cannot_be_negative(): void
    {
        $totals = app(InvoiceTotalsCalculator::class)->calculate(['100.10', '20.20'], '10.30', '5.40');
        $this->assertSame(['subtotal' => '120.30', 'discount_amount' => '10.30', 'tax_amount' => '5.40', 'total_amount' => '115.40'], $totals);
        $this->expectException(InvalidArgumentException::class);
        app(InvoiceTotalsCalculator::class)->calculate(['10'], '11');
    }

    public function test_currency_mismatch_and_empty_generation_fail_without_empty_invoice(): void
    {
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create();
        $this->activity($project, ['currency_snapshot' => 'IRT']);
        $this->activity($project, ['currency_snapshot' => 'USD']);
        try {
            $this->generate($customer);
            $this->fail();
        } catch (DomainException $exception) {
            $this->assertSame('currency_inconsistency', $exception->getMessage());
        }
        $this->assertDatabaseCount('invoices', 0);

        $empty = Customer::factory()->create();
        try {
            $this->generate($empty);
            $this->fail();
        } catch (DomainException $exception) {
            $this->assertSame('no_billable_activities', $exception->getMessage());
        }
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_double_billing_is_blocked_until_invoice_is_cancelled(): void
    {
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create();
        $activity = $this->activity($project);
        $invoice = $this->generate($customer);
        try {
            $this->generate($customer);
            $this->fail();
        } catch (DomainException $exception) {
            $this->assertSame('no_billable_activities', $exception->getMessage());
        }

        app(InvoiceLifecycle::class)->cancel($invoice);
        $replacement = $this->generate($customer);
        $this->assertNotSame($invoice->id, $replacement->id);
        $this->assertSame($activity->id, $replacement->items->first()->client_project_activity_id);
        $this->assertNotSame($invoice->invoice_number, $replacement->invoice_number);
    }

    public function test_issue_paid_cancel_lifecycle_and_immutability_are_enforced(): void
    {
        $customer = Customer::factory()->create();
        $this->activity(ClientProject::factory()->for($customer)->create());
        $invoice = $this->generate($customer);
        $issued = app(InvoiceLifecycle::class)->issue($invoice);
        $this->assertSame(InvoiceStatus::Issued, $issued->status);
        $this->assertNotNull($issued->issued_at);

        $item = $issued->items()->firstOrFail();
        $this->expectException(LogicException::class);
        $item->update(['total_amount' => '1']);
    }

    public function test_issued_can_be_paid_but_paid_cannot_be_cancelled(): void
    {
        $customer = Customer::factory()->create();
        $this->activity(ClientProject::factory()->for($customer)->create());
        $paid = app(InvoiceLifecycle::class)->markPaid(app(InvoiceLifecycle::class)->issue($this->generate($customer)));
        $this->assertSame(InvoiceStatus::Paid, $paid->status);
        $this->assertNotNull($paid->paid_at);
        $this->expectException(DomainException::class);
        app(InvoiceLifecycle::class)->cancel($paid);
    }

    public function test_activity_and_service_changes_or_deletion_do_not_change_invoice_item_snapshot(): void
    {
        $customer = Customer::factory()->create();
        $activity = $this->activity(ClientProject::factory()->for($customer)->create(), ['title' => 'عنوان اصلی', 'service_name_snapshot' => 'خدمت اصلی']);
        $invoice = app(InvoiceLifecycle::class)->issue($this->generate($customer));
        try {
            $activity->update(['service_name_snapshot' => 'خدمت جدید', 'total_amount' => '999']);
            $this->fail('Claimed activity should be locked.');
        } catch (LogicException) {
            $this->assertTrue(true);
        }
        app(InvoiceLifecycle::class)->cancel($invoice);
        $activity->update(['title' => 'عنوان جدید', 'service_name_snapshot' => 'خدمت جدید', 'total_amount' => '999']);
        $activity->delete();
        $item = $invoice->items()->firstOrFail()->fresh();
        $this->assertNull($item->client_project_activity_id);
        $this->assertSame('عنوان اصلی', $item->activity_title_snapshot);
        $this->assertSame('خدمت اصلی', $item->service_name_snapshot);
        $this->assertSame('100.00', $item->total_amount);
    }

    public function test_client_only_sees_issued_or_paid_invoices_for_accessible_customers(): void
    {
        $user = User::factory()->client()->create();
        $own = Customer::factory()->create();
        $foreign = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($own, $user, 'owner');
        foreach ([$own, $foreign] as $customer) {
            $this->activity(ClientProject::factory()->for($customer)->create());
        }
        $ownInvoice = app(InvoiceLifecycle::class)->issue($this->generate($own));
        $foreignInvoice = app(InvoiceLifecycle::class)->issue($this->generate($foreign));
        $draftActivity = $this->activity(ClientProject::factory()->for($own)->create(), ['activity_date' => '2026-08-15']);
        $draft = $this->generate($own);

        $this->actingAs($user, 'client')->get(route('account.invoices.index'))->assertOk()->assertSee($ownInvoice->invoice_number)->assertDontSee($foreignInvoice->invoice_number)->assertDontSee($draft->invoice_number);
        $this->actingAs($user, 'client')->get(route('account.invoices.show', $ownInvoice))->assertOk();
        $this->actingAs($user, 'client')->get(route('account.invoices.show', $foreignInvoice))->assertNotFound();
        $this->assertNotNull($draftActivity);
    }

    public function test_draft_review_can_remove_and_add_candidates_without_losing_claim_integrity(): void
    {
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create();
        $first = $this->activity($project);
        $invoice = $this->generate($customer);
        $item = $invoice->items()->firstOrFail();
        app(DraftInvoiceItems::class)->remove($item);
        $this->assertDatabaseMissing('invoice_activity_claims', ['client_project_activity_id' => $first->id]);
        $this->assertSame('0.00', $invoice->fresh()->total_amount);

        $second = $this->activity($project, ['title' => 'فعالیت افزوده']);
        app(DraftInvoiceItems::class)->add($invoice->fresh(), $second);
        $this->assertDatabaseHas('invoice_activity_claims', ['client_project_activity_id' => $second->id, 'invoice_id' => $invoice->id]);
        $this->assertSame('100.00', $invoice->fresh()->total_amount);
    }

    public function test_admin_create_flow_recovers_from_completed_uninvoiced_cycle(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create(['monthly_hour_limit_minutes' => 60, 'start_date' => '2026-08-01']);
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        ClientProjectActivity::factory()->for($project, 'project')->create(['client_project_cycle_id' => $cycle->id, 'activity_date' => '2026-08-10', 'duration_minutes' => 60, 'currency_snapshot' => 'IRT', 'unit_price_snapshot' => '100', 'total_amount' => '100', 'pricing_mode_snapshot' => 'fixed', 'service_unit_snapshot' => 'fixed']);
        app(InvoiceLifecycle::class)->cancel($cycle->fresh()->invoice);
        $this->actingAs($admin);

        Livewire::test(CreateInvoice::class)->fillForm(['customer_id' => $customer->id, 'client_project_id' => $project->id, 'client_project_cycle_id' => $cycle->id])->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseHas('invoices', ['customer_id' => $customer->id, 'client_project_cycle_id' => $cycle->id, 'status' => 'draft', 'total_amount' => '100.00']);
    }

    private function activity(ClientProject $project, array $overrides = []): ClientProjectActivity
    {
        return ClientProjectActivity::factory()->for($project, 'project')->create(['activity_date' => '2026-08-10', 'duration_minutes' => 60, 'currency_snapshot' => 'IRT', 'unit_price_snapshot' => '100', 'total_amount' => '100', 'pricing_mode_snapshot' => 'fixed', 'service_unit_snapshot' => 'fixed', ...$overrides]);
    }

    private function generate(Customer $customer): Invoice
    {
        return app(MonthlyInvoiceGenerator::class)->generate($customer, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-08-31'));
    }
}
