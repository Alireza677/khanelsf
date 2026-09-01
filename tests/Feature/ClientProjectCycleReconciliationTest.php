<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectActivityResource;
use App\Filament\Resources\ClientProjectResource\Pages\EditClientProject;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\User;
use App\Services\ClientProjectCycleReconciler;
use App\Services\ClientProjectCycleResolver;
use App\Services\ClientProjectCycleUsage;
use App\Services\CustomerMembershipManager;
use App\Services\InvoiceLifecycle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class ClientProjectCycleReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_uses_contract_cycle_across_gregorian_month_crossover(): void
    {
        CarbonImmutable::setTestNow('2026-09-01 12:00:00');
        try {
            [$user, $customer] = $this->member();
            $project = $this->project($customer, 900, '2026-08-28'); // 1405/06/06
            $cycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-30'));
            $this->activity($project, '2026-08-30', 120, $cycle->id);
            $this->activity($project, '2026-08-31', 480, $cycle->id);

            $this->actingAs($user, 'client')->get(route('account.services.index'))
                ->assertOk()
                ->assertSee('مصرف دوره جاری')
                ->assertSee('10 ساعت')
                ->assertSee('۱۴۰۵/۰۶/۰۶ تا ۱۴۰۵/۰۷/۰۶');

            $this->assertSame(600, app(ClientProjectCycleUsage::class)->consumed($cycle));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_schedule_shift_reconciles_mutable_activities_without_changing_activity_dates(): void
    {
        $project = $this->project(Customer::factory()->create(), 900, '2026-08-23'); // 1405/06/01
        $oldCycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-27'));
        $beforeStart = $this->activity($project, '2026-08-27', 60, $oldCycle->id); // 1405/06/05
        $inside = $this->activity($project, '2026-08-30', 120, $oldCycle->id); // 1405/06/08

        app(ClientProjectCycleReconciler::class)->updateProject($project, ['start_date' => '2026-08-28']);

        $this->assertSame('2026-08-27', $beforeStart->fresh()->activity_date->toDateString());
        $this->assertNull($beforeStart->fresh()->client_project_cycle_id);
        $this->assertSame('2026-08-30', $inside->fresh()->activity_date->toDateString());
        $newCycle = $inside->fresh()->cycle;
        $this->assertSame('2026-08-28', $newCycle->starts_at->toDateString());
        $this->assertSame('2026-09-28', $newCycle->ends_at->toDateString());
        $this->assertSame(120, app(ClientProjectCycleUsage::class)->consumed($newCycle));
        $this->assertDatabaseMissing('client_project_cycles', ['id' => $oldCycle->id]);
    }

    public function test_filament_project_edit_runs_schedule_reconciliation_service(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = $this->project(Customer::factory()->create(), 900, '2026-08-23');
        $oldCycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-30'));
        $activity = $this->activity($project, '2026-08-30', 60, $oldCycle->id);

        Livewire::test(EditClientProject::class, ['record' => $project->getRouteKey()])
            ->fillForm(['start_date' => '2026-08-28'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-08-28', $project->fresh()->start_date->toDateString());
        $this->assertSame('2026-08-28', $activity->fresh()->cycle->starts_at->toDateString());
    }

    public function test_activity_date_edit_reassigns_unclaimed_activity_and_refreshes_both_cycles(): void
    {
        $project = $this->project(Customer::factory()->create(), 900, '2026-08-28');
        $firstCycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-30'));
        $activity = $this->activity($project, '2026-08-30', 60, $firstCycle->id);

        $data = ClientProjectActivityResource::applyCycleFormState([
            'client_project_id' => $project->id,
            'activity_date' => '2026-09-29',
            'duration_minutes' => 60,
        ], $activity);
        $activity->update(['activity_date' => '2026-09-29', 'client_project_cycle_id' => $data['client_project_cycle_id']]);

        $this->assertNotSame($firstCycle->id, $activity->fresh()->client_project_cycle_id);
        $this->assertSame(0, app(ClientProjectCycleUsage::class)->consumed($firstCycle->fresh()));
        $this->assertTrue($activity->fresh()->cycle->containsDate($activity->fresh()->activity_date));
    }

    public function test_project_edit_resolves_destination_project_cycle(): void
    {
        $customer = Customer::factory()->create();
        $source = $this->project($customer, 900, '2026-08-23');
        $destination = $this->project($customer, 900, '2026-08-28');
        $sourceCycle = app(ClientProjectCycleResolver::class)->resolveForDate($source, CarbonImmutable::parse('2026-08-30'));
        $activity = $this->activity($source, '2026-08-30', 60, $sourceCycle->id);

        $data = ClientProjectActivityResource::applyCycleFormState([
            'client_project_id' => $destination->id,
            'activity_date' => '2026-08-30',
            'duration_minutes' => 60,
        ], $activity);
        $activity->update(['client_project_id' => $destination->id, 'client_project_cycle_id' => $data['client_project_cycle_id']]);

        $this->assertSame($destination->id, $activity->fresh()->client_project_id);
        $this->assertTrue($activity->fresh()->cycle->containsDate($activity->fresh()->activity_date));
        $this->assertSame($destination->id, $activity->fresh()->cycle->client_project_id);
    }

    public function test_null_legacy_activity_is_reconciled_deterministically_and_command_dry_run_does_not_write(): void
    {
        $project = $this->project(Customer::factory()->create(), 900, '2026-08-28');
        $activity = $this->activity($project, '2026-08-30', 60);

        $this->artisan('client-project-cycles:reconcile', ['projectId' => $project->id])
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('Protected')
            ->assertSuccessful();
        $this->assertNull($activity->fresh()->client_project_cycle_id);

        $this->artisan('client-project-cycles:reconcile', ['projectId' => $project->id, '--apply' => true])
            ->assertSuccessful();
        $this->assertTrue($activity->fresh()->cycle->containsDate($activity->fresh()->activity_date));
    }

    public function test_reconciliation_can_swap_stale_full_cycle_assignments_without_false_overflow(): void
    {
        $project = $this->project(Customer::factory()->create(), 60, '2026-08-28');
        $firstCycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-30'));
        $secondCycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-09-29'));
        $firstActivity = $this->activity($project, '2026-08-30', 60, $firstCycle->id);
        $secondActivity = $this->activity($project, '2026-09-29', 60, $secondCycle->id);

        DB::table('client_project_activities')->whereKey($firstActivity->id)->update(['client_project_cycle_id' => $secondCycle->id]);
        DB::table('client_project_activities')->whereKey($secondActivity->id)->update(['client_project_cycle_id' => $firstCycle->id]);

        app(ClientProjectCycleReconciler::class)->reconcile($project);

        $this->assertSame($firstCycle->id, $firstActivity->fresh()->client_project_cycle_id);
        $this->assertSame($secondCycle->id, $secondActivity->fresh()->client_project_cycle_id);
        $this->assertSame(60, app(ClientProjectCycleUsage::class)->consumed($firstCycle));
        $this->assertSame(60, app(ClientProjectCycleUsage::class)->consumed($secondCycle));
    }

    public function test_claimed_activity_date_is_immutable(): void
    {
        $project = $this->project(Customer::factory()->create(), 60, '2026-08-28');
        $cycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-30'));
        $activity = $this->billableActivity($project, '2026-08-30', 60, $cycle->id);
        $this->assertDatabaseHas('invoice_activity_claims', ['client_project_activity_id' => $activity->id]);

        $this->expectException(LogicException::class);
        $activity->update(['activity_date' => '2026-08-31']);
    }

    public function test_invoiced_cycle_blocks_conflicting_project_schedule_edit(): void
    {
        $project = $this->project(Customer::factory()->create(), 60, '2026-08-28');
        $cycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-30'));
        $this->billableActivity($project, '2026-08-30', 60, $cycle->id);
        app(InvoiceLifecycle::class)->issue($cycle->fresh()->invoice);

        try {
            app(ClientProjectCycleReconciler::class)->updateProject($project, ['start_date' => '2026-08-29']);
            $this->fail('Protected invoice history should block a conflicting schedule edit.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_date', $exception->errors());
        }

        $this->assertSame('2026-08-28', $project->fresh()->start_date->toDateString());
        $this->assertSame('2026-08-28', $cycle->fresh()->starts_at->toDateString());
    }

    public function test_cycle_boundaries_are_start_inclusive_and_end_exclusive(): void
    {
        $project = $this->project(Customer::factory()->create(), 900, '2026-08-28');
        $first = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-28'));
        $second = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-09-28'));

        $this->assertTrue($first->containsDate('2026-08-28'));
        $this->assertFalse($first->containsDate('2026-09-28'));
        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue($second->containsDate('2026-09-28'));
    }

    public function test_dashboard_and_project_detail_use_same_current_cycle_usage_and_exclude_cancelled(): void
    {
        CarbonImmutable::setTestNow('2026-09-01 12:00:00');
        try {
            [$user, $customer] = $this->member();
            $project = $this->project($customer, 900, '2026-08-28');
            $cycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, CarbonImmutable::parse('2026-08-30'));
            $this->activity($project, '2026-08-30', 120, $cycle->id);
            $this->activity($project, '2026-08-31', 300, $cycle->id, ClientProjectActivity::STATUS_CANCELLED);

            $dashboard = $this->actingAs($user, 'client')->get(route('account.services.index'));
            $detail = $this->actingAs($user, 'client')->get(route('account.projects.show', $project));
            $dashboard->assertOk()->assertSee('2 ساعت');
            $detail->assertOk()->assertSee('مصرف دوره جاری')->assertSee('2 ساعت');
            $this->assertSame(120, app(ClientProjectCycleUsage::class)->consumed($cycle));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    private function member(): array
    {
        $user = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        app(CustomerMembershipManager::class)->attach($customer, $user, 'member');

        return [$user, $customer];
    }

    private function project(Customer $customer, int $minutes, string $start): ClientProject
    {
        return ClientProject::factory()->for($customer)->create([
            'monthly_hour_limit_minutes' => $minutes,
            'start_date' => $start,
            'status' => ClientProject::STATUS_ACTIVE,
        ]);
    }

    private function activity(ClientProject $project, string $date, int $minutes, ?int $cycleId = null, string $status = ClientProjectActivity::STATUS_PUBLISHED): ClientProjectActivity
    {
        return ClientProjectActivity::factory()->for($project, 'project')->publishedForClient()->create([
            'client_project_cycle_id' => $cycleId,
            'activity_date' => $date,
            'duration_minutes' => $minutes,
            'status' => $status,
        ]);
    }

    private function billableActivity(ClientProject $project, string $date, int $minutes, int $cycleId): ClientProjectActivity
    {
        return ClientProjectActivity::factory()->for($project, 'project')->create([
            'client_project_cycle_id' => $cycleId,
            'activity_date' => $date,
            'duration_minutes' => $minutes,
            'currency_snapshot' => 'IRT',
            'unit_price_snapshot' => '100',
            'total_amount' => '100',
            'pricing_mode_snapshot' => 'fixed',
            'service_unit_snapshot' => 'fixed',
        ]);
    }
}
