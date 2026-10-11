<?php

namespace Tests\Feature;

use App\Enums\ClientProjectCycleStatus;
use App\Filament\Resources\ClientProjectCycleResource\Pages\ManageDeliveryCycles;
use App\Filament\Resources\ClientProjectWorkItemResource\Pages\ManageWorkItems;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\DeliverableType;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceOffering;
use App\Models\User;
use App\Services\ClientProjectActivityOverage;
use App\Services\ClientProjectAgreementManager;
use App\Services\ClientProjectCycleReconciler;
use App\Services\ClientProjectCycleResolver;
use App\Services\ClientProjectCycleUsage;
use App\Services\ClientProjectDeliveryProgress;
use App\Services\ClientProjectWorkItemGenerator;
use App\Services\ClientProjectWorkItemManager;
use App\Services\CreateClientProjectWithOffering;
use App\Services\RecalculateClientProjectCycle;
use App\Services\ServiceOfferingPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ClientProjectTaskDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00'));
        $this->admin = User::factory()->admin()->create();
    }

    private function project(string $mode = 'tasks', bool $approval = false, string $quantity = '5', string $schedule = 'recurring', bool $mixedUnits = false, ?int $minutes = 60): ClientProject
    {
        $service = Service::create(['name' => 'خدمت عمومی', 'slug' => 'service-'.uniqid(), 'status' => 'draft']);
        $offering = ServiceOffering::create(['service_id' => $service->id, 'name' => 'بسته', 'code' => uniqid(), 'status' => 'active']);
        $type = DeliverableType::create(['name' => 'تحویل عمومی', 'code' => uniqid(), 'unit' => 'session']);
        $draft = app(ServiceOfferingPublisher::class)->newDraft($offering, $this->admin);
        $draft->update(['delivery_mode' => $mode, 'schedule_mode' => $schedule, 'amount' => '12345678901234.1234', 'scheduling_defaults' => []]);
        $draft->deliverables()->create(['deliverable_type_id' => $type->id, 'name' => 'تعهد مستقل', 'quantity' => $quantity, 'unit' => 'session', 'approval_required' => $approval, 'revision_allowance' => 2, 'scheduling_rules' => ['due_day_offset' => 7]]);
        if ($mixedUnits) {
            $other = DeliverableType::create(['name' => 'نوع مستقل دوم', 'code' => uniqid(), 'unit' => 'report']);
            $draft->deliverables()->create(['deliverable_type_id' => $other->id, 'name' => 'تعهد دوم', 'quantity' => '2', 'unit' => 'report']);
        }
        $version = app(ServiceOfferingPublisher::class)->publish($draft, $this->admin);

        return app(CreateClientProjectWithOffering::class)->create([
            'customer_id' => Customer::factory()->create()->id, 'title' => 'پروژه عمومی', 'status' => 'active', 'schedule_mode' => $schedule,
            'cycle_anchor_day' => 6, 'monthly_hour_limit_minutes' => $mode === 'hybrid' ? $minutes : null,
            'start_date' => $schedule === 'fixed_period' ? '2026-10-11' : null, 'end_date' => $schedule === 'fixed_period' ? '2026-12-11' : null,
        ], $version->id, [], null, $this->admin);
    }

    private function transition($item, string $status, ?string $reason = null)
    {
        return app(ClientProjectWorkItemManager::class)->update($item, ['status' => $status, 'reason' => $reason], $item->lock_version, $this->admin);
    }

    public function test_tasks_without_hours_use_jalali_half_open_cycles_and_never_complete_from_zero_minutes(): void
    {
        $project = $this->project();
        $cycle = $project->cycles()->firstOrFail();
        $this->assertNull($project->monthly_hour_limit_minutes);
        $this->assertSame(0, $cycle->allocated_minutes);
        $this->assertSame('2026-09-28', $cycle->starts_at->toDateString());
        $this->assertSame('2026-10-28', $cycle->ends_at->toDateString());
        $this->assertFalse($cycle->containsDate('2026-10-28'));
        app(RecalculateClientProjectCycle::class)->handle($cycle);
        $this->assertSame(ClientProjectCycleStatus::Active, $cycle->fresh()->status);
        $this->assertSame('planned', $cycle->fresh()->delivery_status);
        $this->assertSame('12345678901234.1234', $cycle->agreement_snapshot['amount']);
        $this->assertDatabaseCount('client_project_activities', 0);
        $this->assertDatabaseCount('invoices', 0);
        $next = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-10-28'));
        $this->assertNotSame($cycle->id, $next->id);
    }

    public function test_fixed_period_keeps_one_whole_project_cycle(): void
    {
        $project = $this->project(schedule: 'fixed_period');
        $cycle = $project->cycles()->firstOrFail();
        $resolver = app(ClientProjectCycleResolver::class);
        $this->assertSame($cycle->id, $resolver->resolveForDate($project, CarbonImmutable::parse('2026-11-15'))->id);
        $this->assertNull($resolver->resolveForDate($project, CarbonImmutable::parse('2026-12-11')));
        $this->assertSame(1, $project->cycles()->count());
        $this->assertSame('2026-12-11', $cycle->ends_at->toDateString());
    }

    public function test_hybrid_keeps_hourly_usage_and_overage_separate_from_delivery(): void
    {
        $project = $this->project('hybrid');
        $cycle = $project->cycles()->firstOrFail();
        $activity = ClientProjectActivity::factory()->create(['client_project_id' => $project->id, 'client_project_cycle_id' => $cycle->id, 'activity_date' => '2026-10-11', 'duration_minutes' => 71]);
        $this->assertSame(ClientProjectCycleStatus::Completed, $cycle->fresh()->status);
        $this->assertSame(11, app(ClientProjectCycleUsage::class)->summary($cycle)['overage_minutes']);
        $this->assertSame('planned', $cycle->fresh()->delivery_status);
        app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin);
        $item = $cycle->workItems()->first();
        app(ClientProjectWorkItemManager::class)->update($item, ['activities' => [$activity->id]], 0, $this->admin);
        $this->assertSame(1, $item->activities()->count());
        $this->assertSame(71, $activity->fresh()->duration_minutes);
        $unlimited = $this->project('hybrid', minutes: null);
        $unlimitedCycle = $unlimited->cycles()->firstOrFail();
        $unlimitedActivity = ClientProjectActivity::factory()->create(['client_project_id' => $unlimited->id, 'client_project_cycle_id' => $unlimitedCycle->id, 'activity_date' => '2026-10-11', 'duration_minutes' => 71]);
        $this->assertSame(0, app(ClientProjectCycleUsage::class)->summary($unlimitedCycle)['overage_minutes']);
        $this->assertFalse(app(ClientProjectActivityOverage::class)->isOverage($unlimitedActivity));
        $this->assertSame(ClientProjectCycleStatus::Active, $unlimitedCycle->fresh()->status);
    }

    public function test_generation_and_reconciliation_are_idempotent_and_cancelled_work_is_retained(): void
    {
        $project = $this->project(quantity: '5.5000');
        $cycle = $project->cycles()->firstOrFail();
        $generator = app(ClientProjectWorkItemGenerator::class);
        $this->assertSame(6, $generator->generate($cycle, $this->admin));
        $this->assertSame('0.5000', $cycle->workItems()->latest('id')->first()->quantity);
        $item = $cycle->workItems()->first();
        $this->transition($item, 'cancelled', 'لغو اجرایی');
        $this->assertSame(0, $generator->generate($cycle, $this->admin));
        app(ClientProjectCycleReconciler::class)->reconcile($project);
        app(ClientProjectCycleReconciler::class)->reconcile($project);
        $this->assertSame(1, $project->cycles()->count());
        $this->assertSame(6, $cycle->workItems()->count());
        $this->assertSame('5.5000', $cycle->commitments()->first()->quantity);
        $this->assertSame($cycle->id, $item->fresh()->client_project_cycle_id);
    }

    public function test_future_amendment_only_changes_future_commitments_and_conflicts_are_rejected(): void
    {
        $project = $this->project();
        $cycle = $project->cycles()->firstOrFail();
        $plan = $project->planVersions()->first();
        $lines = $plan->agreement_snapshot['deliverables'];
        $lines[0]['quantity'] = '7';
        $next = app(ClientProjectAgreementManager::class)->append($project, $plan->offeringVersion, ['deliverables' => $lines], 'افزایش تعهد آینده', '2026-10-28', $this->admin);
        $nextCycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-10-28'));
        $this->assertSame('5.0000', $cycle->commitments()->first()->quantity);
        $this->assertSame('7.0000', $nextCycle->commitments()->first()->quantity);
        $this->assertSame($next->id, $nextCycle->client_project_plan_version_id);
        $plan->offeringVersion->offering->update(['name' => 'نام تازه']);
        $this->assertSame('بسته', $cycle->fresh()->agreement_snapshot['offering']['name']);
        foreach (['2026-10-28', '2026-11-05'] as $date) {
            try {
                app(ClientProjectAgreementManager::class)->append($project, $plan->offeringVersion, [], 'تداخل', $date, $this->admin);
                $this->fail('Conflicting dates must be rejected.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('effective_from', $error->errors());
            }
        }
    }

    public function test_different_units_keep_independent_progress_and_future_cycles_protect_their_plan(): void
    {
        $project = $this->project(mixedUnits: true);
        $cycle = $project->cycles()->firstOrFail();
        app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin);
        $item = $cycle->workItems()->whereHas('commitment', fn ($query) => $query->where('unit', 'report'))->first();
        $item = $this->transition($item, 'ready');
        $item = $this->transition($item, 'in_progress');
        $this->transition($item, 'completed');
        $rows = app(ClientProjectDeliveryProgress::class)->cycle($cycle);
        $this->assertSame('5.0000', $rows[0]['remaining']);
        $this->assertSame('session', $rows[0]['unit']);
        $this->assertSame('1.0000', $rows[1]['remaining']);
        $this->assertSame('report', $rows[1]['unit']);
        app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-11-28'));
        $plan = $project->planVersions()->first();
        $this->expectException(ValidationException::class);
        app(ClientProjectAgreementManager::class)->append($project, $plan->offeringVersion, ['amount' => '25'], 'تغییر پیش از دوره برنامه‌ریزی‌شده', '2026-10-28', $this->admin);
    }

    public function test_reconciliation_rejects_schedule_changes_and_preserves_commitments_and_work(): void
    {
        $project = $this->project();
        $cycle = $project->cycles()->firstOrFail();
        app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin);
        $before = $cycle->fresh()->getAttributes();
        try {
            app(ClientProjectCycleReconciler::class)->updateProject($project, ['cycle_anchor_day' => 8]);
            $this->fail('Committed schedules must remain stable.');
        } catch (ValidationException) {
            $this->assertSame(6, $project->fresh()->cycle_anchor_day);
        }
        app(ClientProjectCycleReconciler::class)->reconcile($project);
        $this->assertSame($before, $cycle->fresh()->getAttributes());
        $this->assertSame(5, $cycle->workItems()->count());
        $this->expectException(\LogicException::class);
        $cycle->delete();
    }

    public function test_progress_excludes_extras_cancellation_and_submission_and_distinguishes_acceptance(): void
    {
        $project = $this->project(approval: true);
        $cycle = $project->cycles()->firstOrFail();
        app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin);
        $item = $cycle->workItems()->first();
        $item = $this->transition($item, 'ready');
        $item = $this->transition($item, 'in_progress');
        $item = $this->transition($item, 'submitted');
        $this->assertNotNull($item->operational_completed_at);
        $this->assertNull($item->accepted_at);
        $progress = app(ClientProjectDeliveryProgress::class)->commitment($cycle->commitments()->first());
        $this->assertSame('1.0000', $progress['operational_completed']);
        $this->assertSame('0.0000', $progress['completed']);
        $this->assertSame('5.0000', $progress['remaining']);
        $extra = app(ClientProjectWorkItemManager::class)->createExtra($cycle, ['title' => 'کار اضافی', 'quantity' => '20', 'unit' => 'report'], $this->admin);
        $extra = $this->transition($extra, 'ready');
        $extra = $this->transition($extra, 'in_progress');
        $this->transition($extra, 'completed');
        $this->assertSame('0.0000', app(ClientProjectDeliveryProgress::class)->commitment($cycle->commitments()->first())['completed']);
        $this->assertSame('awaiting_approval', $cycle->fresh()->delivery_status);
        $this->expectException(ValidationException::class);
        $this->transition($item, 'accepted');
    }

    public function test_execution_transitions_and_optimistic_version_prevent_stale_updates(): void
    {
        $cycle = $this->project(quantity: '1')->cycles()->firstOrFail();
        app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin);
        $item = $cycle->workItems()->first();
        try {
            $this->transition($item, 'completed');
            $this->fail('Cannot skip execution.');
        } catch (ValidationException) {
            $this->assertSame('planned', $item->fresh()->status->value);
        }
        $item = $this->transition($item, 'ready');
        $item = $this->transition($item, 'blocked', 'مانع اجرایی');
        $item = $this->transition($item, 'in_progress');
        $item = $this->transition($item, 'completed');
        $this->assertSame('fulfilled', $cycle->fresh()->delivery_status);
        $this->assertSame(ClientProjectCycleStatus::Active, $cycle->fresh()->status);
        $this->assertSame(5, $item->events()->count());
        $this->expectException(ValidationException::class);
        app(ClientProjectWorkItemManager::class)->update($item, ['title' => 'ویرایش قدیمی'], 0, $this->admin);
    }

    public function test_period_end_is_overdue_and_never_fulfills_or_moves_work(): void
    {
        $project = $this->project();
        $cycle = $project->cycles()->firstOrFail();
        app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin);
        $this->travelTo(CarbonImmutable::parse('2026-11-01'));
        app(RecalculateClientProjectCycle::class)->refreshOverdue();
        app(ClientProjectCycleReconciler::class)->ensureActiveRecurringCycles();
        $this->assertSame('overdue', $cycle->fresh()->delivery_status);
        $this->assertSame(ClientProjectCycleStatus::Active, $cycle->fresh()->status);
        $this->assertSame(5, $cycle->workItems()->count());
        $this->assertSame('5.0000', app(ClientProjectDeliveryProgress::class)->commitment($cycle->commitments()->first())['remaining']);
        $this->assertSame(2, $project->cycles()->count());
    }

    public function test_admin_ui_generates_edits_assigns_and_displays_commitment_progress(): void
    {
        $cycle = $this->project()->cycles()->firstOrFail();
        $this->actingAs($this->admin);
        Livewire::test(ManageDeliveryCycles::class)->assertOk()->callTableAction('generate', $cycle)->assertHasNoTableActionErrors();
        $item = $cycle->workItems()->firstOrFail();
        Livewire::test(ManageWorkItems::class)->assertOk()->callTableAction('edit', $item, data: ['title' => 'عنوان تازه', 'status' => 'ready', 'assignees' => [$this->admin->id], 'lock_version' => 0])->assertHasNoTableActionErrors();
        $this->assertSame('ready', $item->fresh()->status->value);
        $this->assertSame(1, $item->assignees()->count());
        Livewire::test(ManageWorkItems::class)->mountTableAction('view', $item)->assertSee('عنوان تازه')->assertSee('تعهد مستقل')->assertSee('تاریخچه تغییرات');
        Livewire::test(ManageDeliveryCycles::class)->mountTableAction('view', $cycle)->assertSee('باقی‌مانده تعهد');
        Livewire::test(ManageWorkItems::class)->callAction('create', data: ['client_project_cycle_id' => $cycle->id, 'title' => 'کار دستی', 'visibility' => 'internal', 'assignees' => [$this->admin->id]])->assertHasNoActionErrors();
        $this->assertSame(6, $cycle->workItems()->count());
    }

    public function test_client_cannot_manage_work_and_inactive_admin_cannot_be_assigned(): void
    {
        $cycle = $this->project()->cycles()->firstOrFail();
        try {
            app(ClientProjectWorkItemGenerator::class)->generate($cycle, User::factory()->create());
            $this->fail('Client must not manage work.');
        } catch (AuthorizationException) {
            $this->assertSame(0, $cycle->workItems()->count());
        }
        $this->expectException(ValidationException::class);
        app(ClientProjectWorkItemManager::class)->createExtra($cycle, ['title' => 'اضافی', 'assignees' => [User::factory()->admin()->create(['status' => 'inactive'])->id]], $this->admin);
    }

    public function test_additive_migration_round_trip_preserves_legacy_cycle_activity_and_snapshots(): void
    {
        $project = ClientProject::factory()->create(['status' => 'active', 'start_date' => '2026-10-01', 'monthly_hour_limit_minutes' => 60]);
        $cycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, now());
        $activity = ClientProjectActivity::factory()->create(['client_project_id' => $project->id, 'client_project_cycle_id' => $cycle->id, 'activity_date' => '2026-10-11']);
        $invoice = Invoice::create(['customer_id' => $project->customer_id, 'client_project_cycle_id' => $cycle->id, 'invoice_number' => 'LEGACY-CYCLE', 'period_start' => '2026-10-01', 'period_end' => '2026-11-01', 'currency' => 'IRT', 'subtotal' => '42.50', 'total_amount' => '42.50', 'status' => 'draft']);
        $item = $invoice->items()->create(['client_project_activity_id' => $activity->id, 'activity_title_snapshot' => 'عنوان تاریخی', 'unit_price' => '42.5000', 'total_amount' => '42.50', 'currency' => 'IRT']);
        DB::table('invoice_cycle_claims')->insert(['client_project_cycle_id' => $cycle->id, 'invoice_id' => $invoice->id, 'created_at' => now()]);
        $records = [$project, $cycle, $activity, $invoice, $item];
        $before = array_map(fn ($record) => $record->fresh()->getAttributes(), $records);
        $migration = require database_path('migrations/2026_10_11_200000_add_task_delivery_cycles_and_work_items.php');
        $migration->down();
        $migration->up();
        foreach ($records as $index => $record) {
            $after = $record->fresh()->getAttributes();
            ksort($before[$index]);
            ksort($after);
            $this->assertSame($before[$index], $after);
        }
        $this->assertDatabaseHas('invoice_cycle_claims', ['client_project_cycle_id' => $cycle->id, 'invoice_id' => $invoice->id]);
    }
}
