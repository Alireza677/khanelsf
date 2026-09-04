<?php

namespace Tests\Feature;

use App\Enums\ClientProjectCycleStatus;
use App\Enums\InvoiceStatus;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\ClientProjectCycle;
use App\Models\Customer;
use App\Services\ClientProjectActivityOverage;
use App\Services\ClientProjectCycleResolver;
use App\Services\ClientProjectCycleUsage;
use App\Services\InvoiceLifecycle;
use App\Services\ProjectCycleInvoiceGenerator;
use App\Services\RecalculateClientProjectCycle;
use Carbon\CarbonImmutable;
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

    public function test_reaching_allocation_completes_quota_without_automatically_freezing_cycle(): void
    {
        $customer = Customer::factory()->create();
        $project = $this->project(900, '2026-08-05', $customer);
        $other = $this->project(300, '2026-08-05', $customer);
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        $otherCycle = app(ClientProjectCycleResolver::class)->resolve($other, CarbonImmutable::parse('2026-08-10'));
        $this->activity($other, $otherCycle->id, 300);
        $this->activity($project, $cycle->id, 840);
        $this->assertDatabaseCount('invoices', 0);
        $this->activity($project, $cycle->id, 60);
        $this->assertSame(ClientProjectCycleStatus::Completed, $cycle->fresh()->status);
        $this->assertDatabaseCount('invoices', 0);

        $invoice = app(ProjectCycleInvoiceGenerator::class)->generate($cycle->fresh());
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($cycle->id, $invoice->client_project_cycle_id);
        $this->assertSame($cycle->starts_at->toDateString(), $invoice->period_start->toDateString());
        $this->assertCount(2, $invoice->items);
        $this->assertTrue($invoice->items->every(fn ($item) => $item->client_project_id === $project->id));
        $this->assertSame($invoice->id, app(ProjectCycleInvoiceGenerator::class)->generate($cycle)->id);
    }

    public function test_activity_outside_overdue_cycle_resolves_to_the_cycle_containing_its_date(): void
    {
        $project = $this->project(120, '2026-07-06');
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-07-10'));
        $this->activity($project, $cycle->id, 60);
        app(RecalculateClientProjectCycle::class)->handle($cycle);
        $resolved = app(ClientProjectCycleResolver::class)->resolve($project, now()->toImmutable(), 60);
        $this->assertNotSame($cycle->id, $resolved->id);
        $this->assertTrue($resolved->containsDate(now()->toImmutable()));
        $this->assertFalse($cycle->containsDate(now()->toImmutable()));
        $this->assertSame(ClientProjectCycleStatus::Overdue, $cycle->fresh()->status);
    }

    public function test_activity_can_overflow_allocation_and_usage_reports_overage(): void
    {
        $project = $this->project(60, '2026-08-05');
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        $withinQuota = $this->activity($project, $cycle->id, 30);
        $resolved = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-11'), 31);
        $crossingQuota = $this->activity($project, $resolved->id, 31);
        $overage = $this->activity($project, $resolved->id, 10);

        $summary = app(ClientProjectCycleUsage::class)->summary($cycle->fresh());
        $this->assertSame(60, $summary['allocated_minutes']);
        $this->assertSame(71, $summary['used_minutes']);
        $this->assertSame(0, $summary['remaining_minutes']);
        $this->assertSame(11, $summary['overage_minutes']);
        $this->assertFalse(app(ClientProjectActivityOverage::class)->isOverage($withinQuota));
        $this->assertTrue(app(ClientProjectActivityOverage::class)->isOverage($crossingQuota));
        $this->assertTrue(app(ClientProjectActivityOverage::class)->isOverage($overage));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_issue_and_cancel_update_cycle_and_release_for_replacement(): void
    {
        $project = $this->project(60, '2026-08-05');
        $cycle = app(ClientProjectCycleResolver::class)->resolve($project, CarbonImmutable::parse('2026-08-10'));
        $this->activity($project, $cycle->id, 60);
        $invoice = app(InvoiceLifecycle::class)->issue(app(ProjectCycleInvoiceGenerator::class)->generate($cycle->fresh()));
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
        app(ProjectCycleInvoiceGenerator::class)->generate($cycle->fresh());
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
        $cycle = ClientProjectCycle::findOrFail($cycleId);

        return ClientProjectActivity::factory()->for($project, 'project')->create(['client_project_cycle_id' => $cycleId, 'activity_date' => $cycle->starts_at->addDay()->toDateString(), 'duration_minutes' => $minutes, 'currency_snapshot' => 'IRT', 'unit_price_snapshot' => '100', 'total_amount' => '100', 'pricing_mode_snapshot' => 'fixed', 'service_unit_snapshot' => 'fixed', ...$extra]);
    }
}
