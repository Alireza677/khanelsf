<?php

namespace Tests\Feature;

use App\Enums\ClientProjectCycleStatus;
use App\Enums\InvoiceStatus;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Services\ClientProjectCycleResolver;
use App\Services\ClientProjectCycleUsage;
use App\Services\InvoiceLifecycle;
use App\Services\ProjectCycleInvoiceGenerator;
use App\Services\RecalculateClientProjectCycle;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientProjectCycleBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_cycle_snapshots_allocation_and_preserves_non_calendar_jalali_boundary(): void
    {
        $project = $this->project(900, '2026-08-05'); // 14 Mordad 1405
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        $project->update(['monthly_hour_limit_minutes' => 1200]);
        $this->assertSame('2026-08-05', $cycle->starts_at->toDateString());
        $this->assertSame('2026-09-05', $cycle->ends_at->toDateString());
        $this->assertSame(900, $cycle->fresh()->allocated_minutes);
    }

    public function test_cancelled_activity_is_excluded_and_deadline_only_marks_overdue(): void
    {
        $project = $this->project(900, '2026-07-06');
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-07-10'));
        $this->activity($project, $cycle->id, 720);
        $this->activity($project, $cycle->id, 180, ['status' => ClientProjectActivity::STATUS_CANCELLED]);
        app(RecalculateClientProjectCycle::class)->handle($cycle);
        $summary = app(ClientProjectCycleUsage::class)->summary($cycle);
        $this->assertSame(720, $summary['consumed_minutes']);
        $this->assertSame(180, $summary['remaining_minutes']);
        $this->assertSame(ClientProjectCycleStatus::Overdue, $cycle->fresh()->status);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_completion_before_or_after_deadline_creates_one_cycle_scoped_draft(): void
    {
        $customer = Customer::factory()->create();
        $project = $this->project(900, '2026-08-05', $customer);
        $other = $this->project(300, '2026-08-05', $customer);
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        $otherCycle = app(ClientProjectCycleResolver::class)->resolve($other, CarbonImmutable::parse('2026-08-10'));
        $this->activity($other, $otherCycle->id, 300);
        $this->activity($project, $cycle->id, 840);
        $this->assertDatabaseCount('invoices', 1); // other project only
        $this->activity($project, $cycle->id, 60);
        $invoice = $cycle->fresh()->invoice;
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($cycle->id, $invoice->client_project_cycle_id);
        $this->assertSame($cycle->starts_at->toDateString(), $invoice->period_start->toDateString());
        $this->assertCount(2, $invoice->items);
        $this->assertTrue($invoice->items->every(fn ($item) => $item->client_project_id === $project->id));
        $this->assertSame($invoice->id, app(ProjectCycleInvoiceGenerator::class)->generate($cycle)->id);
    }

    public function test_overdue_cycle_accepts_later_activity_and_completes(): void
    {
        $project = $this->project(120, '2026-07-06');
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-07-10'));
        $this->activity($project, $cycle->id, 60);
        app(RecalculateClientProjectCycle::class)->handle($cycle);
        $resolved = app(ClientProjectCycleResolver::class)->resolve($project, now()->toImmutable(), 60);
        $this->assertSame($cycle->id, $resolved->id);
        $this->activity($project, $cycle->id, 60);
        $this->assertSame(ClientProjectCycleStatus::Completed, $cycle->fresh()->status);
        $this->assertNotNull($cycle->fresh()->invoice);
    }

    public function test_activity_cannot_silently_overflow_remaining_allocation(): void
    {
        $project = $this->project(60, '2026-08-05');
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        $this->activity($project, $cycle->id, 30);
        $this->expectException(DomainException::class);
        app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-11'), 31);
    }

    public function test_issue_and_cancel_update_cycle_and_release_for_replacement(): void
    {
        $project = $this->project(60, '2026-08-05');
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        $this->activity($project, $cycle->id, 60);
        $invoice = app(InvoiceLifecycle::class)->issue($cycle->fresh()->invoice);
        $this->assertSame(ClientProjectCycleStatus::Invoiced, $cycle->fresh()->status);
        app(InvoiceLifecycle::class)->cancel($invoice);
        $this->assertSame(ClientProjectCycleStatus::Completed, $cycle->fresh()->status);
        $replacement = app(ProjectCycleInvoiceGenerator::class)->generate($cycle->fresh());
        $this->assertNotSame($invoice->id, $replacement->id);
    }

    public function test_claimed_activity_financial_fields_are_locked_but_notes_remain_editable(): void
    {
        $project = $this->project(60, '2026-08-05');
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        $activity = $this->activity($project, $cycle->id, 60);
        $activity->update(['internal_notes' => 'مجاز']);
        $this->assertSame('مجاز', $activity->fresh()->internal_notes);
        $this->expectException(\LogicException::class);
        $activity->update(['duration_minutes' => 30]);
    }

    public function test_legacy_activity_and_invoice_without_cycle_remain_valid(): void
    {
        $activity = ClientProjectActivity::factory()->create();
        $this->assertNull($activity->client_project_cycle_id);
        $this->assertDatabaseCount('client_project_cycles', 0);
    }

    private function project(int $minutes, string $start, ?Customer $customer = null): ClientProject
    {
        return ClientProject::factory()->for($customer ?? Customer::factory())->create(['monthly_hour_limit_minutes' => $minutes, 'start_date' => $start]);
    }

    private function activity(ClientProject $project, int $cycleId, int $minutes, array $extra = []): ClientProjectActivity
    {
        return ClientProjectActivity::factory()->for($project, 'project')->create(['client_project_cycle_id' => $cycleId, 'duration_minutes' => $minutes, 'currency_snapshot' => 'IRT', 'unit_price_snapshot' => '100', 'total_amount' => '100', 'pricing_mode_snapshot' => 'fixed', 'service_unit_snapshot' => 'fixed', ...$extra]);
    }
}
