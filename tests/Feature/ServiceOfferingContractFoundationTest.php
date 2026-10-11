<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectResource\Pages\CreateClientProject;
use App\Filament\Resources\ClientProjectResource\Pages\EditClientProject;
use App\Filament\Resources\ClientProjectResource\Pages\ViewClientProject;
use App\Filament\Resources\DeliverableTypeResource\Pages\ManageDeliverableTypes;
use App\Filament\Resources\ServiceOfferingResource\Pages\CreateServiceOffering;
use App\Filament\Resources\ServiceOfferingResource\Pages\ViewServiceOffering;
use App\Filament\Resources\ServiceOfferingVersionResource\Pages\EditServiceOfferingVersion;
use App\Filament\Resources\ServiceOfferingVersionResource\Pages\ViewServiceOfferingVersion;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\DeliverableType;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceOffering;
use App\Models\ServiceOfferingVersion;
use App\Models\User;
use App\Services\ClientProjectAgreementManager;
use App\Services\ClientProjectCycleReconciler;
use App\Services\ClientProjectCycleResolver;
use App\Services\CreateClientProjectWithOffering;
use App\Services\ServiceOfferingPublisher;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class ServiceOfferingContractFoundationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    private function draft(string $mode = 'tasks'): ServiceOfferingVersion
    {
        $service = Service::create(['name' => 'خدمت عمومی', 'slug' => 'service-'.uniqid(), 'status' => 'draft', 'default_unit_price' => '17.1250']);
        $offering = ServiceOffering::create(['service_id' => $service->id, 'name' => 'بسته عمومی', 'code' => 'PKG-'.$service->id, 'status' => 'active']);
        $type = DeliverableType::create(['code' => 'TYPE-'.$service->id, 'name' => 'نوع تحویل', 'unit' => 'count']);
        $version = app(ServiceOfferingPublisher::class)->newDraft($offering, $this->admin);
        $version->update(['delivery_mode' => $mode, 'amount' => '12345678901234.1234', 'contract_terms' => 'شرایط اولیه', 'scheduling_defaults' => ['cycle_anchor_day' => 6, 'monthly_hour_limit_minutes' => 60]]);
        $version->deliverables()->create(['deliverable_type_id' => $type->id, 'name' => 'تعهد عمومی', 'quantity' => '4.0000', 'unit' => 'count', 'approval_required' => true, 'revision_allowance' => 2, 'scheduling_rules' => ['due_day_offset' => 5]]);

        return $version;
    }

    private function publish(string $mode = 'tasks'): ServiceOfferingVersion
    {
        return app(ServiceOfferingPublisher::class)->publish($this->draft($mode), $this->admin);
    }

    private function attributes(): array
    {
        return ['customer_id' => Customer::factory()->create()->id, 'title' => 'پروژه قرارداد', 'status' => 'active', 'schedule_mode' => 'recurring', 'cycle_anchor_day' => 6, 'monthly_hour_limit_minutes' => 60];
    }

    public function test_existing_projects_default_to_time_without_catalog_backfill(): void
    {
        $project = ClientProject::factory()->create();
        $this->assertSame('time', $project->fresh()->delivery_mode);
        $this->assertDatabaseCount('service_offerings', 0);
        $this->assertDatabaseCount('client_project_plan_versions', 0);
    }

    public function test_published_version_and_deliverables_reject_stale_edits_reparenting_and_deletion(): void
    {
        $draft = $this->draft();
        $stale = ServiceOfferingVersion::findOrFail($draft->id);
        $published = app(ServiceOfferingPublisher::class)->publish($draft, $this->admin);
        $new = app(ServiceOfferingPublisher::class)->newDraft($published->offering, $this->admin);
        $line = $published->deliverables()->firstOrFail();
        foreach ([
            fn () => $stale->update(['amount' => '1']),
            fn () => $published->delete(),
            fn () => $line->update(['quantity' => '1']),
            fn () => $line->update(['service_offering_version_id' => $new->id]),
            fn () => $line->delete(),
            fn () => $published->deliverables()->create(['deliverable_type_id' => $line->deliverable_type_id, 'name' => 'جدید', 'quantity' => '1', 'unit' => 'count']),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Published data must be immutable.');
            } catch (LogicException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('12345678901234.1234', $published->fresh()->amount);
        $this->assertSame(2, $new->version);
    }

    public function test_catalog_changes_do_not_change_agreement_prices_names_or_deliverables(): void
    {
        $version = $this->publish();
        $project = app(CreateClientProjectWithOffering::class)->create($this->attributes(), $version->id, [], null, $this->admin);
        $plan = $project->planVersions()->firstOrFail();
        $snapshot = $plan->agreement_snapshot;
        $version->offering->update(['name' => 'نام جدید']);
        $version->offering->service->update(['name' => 'خدمت جدید', 'default_unit_price' => '99']);
        $version->deliverables()->first()->type->update(['name' => 'نوع جدید']);
        $next = app(ServiceOfferingPublisher::class)->newDraft($version->offering, $this->admin);
        $next->update(['amount' => '999']);
        $next->deliverables()->first()->update(['quantity' => '8']);
        app(ServiceOfferingPublisher::class)->publish($next, $this->admin);
        $this->assertSame($snapshot, $plan->fresh()->agreement_snapshot);
        $this->assertSame('12345678901234.1234', $snapshot['amount']);
        $this->assertSame('4.0000', $snapshot['deliverables'][0]['quantity']);
        $this->assertSame('نوع تحویل', $snapshot['deliverables'][0]['type_snapshot']['name']);
        $this->assertSame('بسته عمومی', $snapshot['offering']['name']);
    }

    public function test_customer_overrides_require_reason_and_creation_is_atomic(): void
    {
        $version = $this->publish();
        try {
            app(CreateClientProjectWithOffering::class)->create($this->attributes(), $version->id, ['amount' => '12.5000'], null, $this->admin);
            $this->fail('An override needs a reason.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('contract_override_reason', $error->errors());
        }
        $this->assertDatabaseCount('client_projects', 0);
        $project = app(CreateClientProjectWithOffering::class)->create($this->attributes(), $version->id, ['amount' => '12.5000'], 'توافق مشتری', $this->admin);
        $plan = $project->planVersions()->first();
        $this->assertSame('12.5000', $plan->agreement_snapshot['amount']);
        $this->assertSame('12345678901234.1234', $plan->catalog_snapshot['amount']);
        $this->assertSame('توافق مشتری', $plan->override_reason);
        $this->assertSame($this->admin->id, $plan->created_by);
    }

    public function test_agreements_are_append_only_with_future_effective_versions(): void
    {
        $version = $this->publish();
        $project = app(CreateClientProjectWithOffering::class)->create($this->attributes(), $version->id, [], null, $this->admin);
        $first = $project->planVersions()->first();
        $second = app(ClientProjectAgreementManager::class)->append($project, $version, ['amount' => '25'], 'تمدید شرایط', $project->cycles()->first()->ends_at->toDateString(), $this->admin);
        $this->assertSame(2, $second->version);
        $this->assertTrue($project->fresh()->currentPlan->is($first));
        $this->expectException(LogicException::class);
        $first->update(['override_reason' => 'بازنویسی']);
    }

    public function test_task_and_hybrid_projects_create_snapshotted_cycles_without_activities_or_invoices(): void
    {
        foreach (['tasks', 'hybrid'] as $mode) {
            $version = $this->publish($mode);
            $project = app(CreateClientProjectWithOffering::class)->create($this->attributes(), $version->id, [], null, $this->admin);
            $this->assertSame($mode, $project->delivery_mode);
            $cycle = app(ClientProjectCycleResolver::class)->resolveForDate($project, now());
            $this->assertSame($mode, $cycle->delivery_mode);
            $this->assertSame(1, $cycle->commitments()->count());
            app(ClientProjectCycleReconciler::class)->reconcile($project);
        }
        app(ClientProjectCycleReconciler::class)->ensureActiveRecurringCycles();
        $this->assertDatabaseCount('client_project_cycles', 2);
        $this->assertDatabaseCount('client_project_activities', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_time_offering_preserves_hourly_cycle_and_operational_service_price(): void
    {
        $version = $this->publish('time');
        $project = app(CreateClientProjectWithOffering::class)->create($this->attributes(), $version->id, [], null, $this->admin);
        $this->assertSame(1, $project->cycles()->count());
        $this->assertSame(60, $project->cycles()->first()->allocated_minutes);
        $this->assertSame('17.1250', $version->offering->service->fresh()->default_unit_price);
    }

    public function test_draft_and_inactive_packages_and_inactive_customers_cannot_create_agreements(): void
    {
        $draft = $this->draft();
        foreach (['draft', 'archived', 'inactive_customer'] as $case) {
            $version = $case === 'draft' ? $draft : $this->publish();
            $attributes = $this->attributes();
            if ($case === 'archived') {
                $version->offering->update(['status' => 'archived']);
            } elseif ($case === 'inactive_customer') {
                Customer::find($attributes['customer_id'])->update(['status' => 'inactive']);
            }
            try {
                app(CreateClientProjectWithOffering::class)->create($attributes, $version->id, [], null, $this->admin);
                $this->fail('Invalid catalog/customer must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('client_projects', 0);
            }
        }
    }

    public function test_client_cannot_publish_or_create_contract_projects(): void
    {
        $client = User::factory()->client()->create();
        $draft = $this->draft();
        try {
            app(ServiceOfferingPublisher::class)->publish($draft, $client);
            $this->fail('Client publication must be denied.');
        } catch (AuthorizationException) {
            $this->assertSame('draft', $draft->fresh()->status);
        }
        $this->expectException(AuthorizationException::class);
        app(CreateClientProjectWithOffering::class)->create($this->attributes(), $draft->id, [], null, $client);
    }

    public function test_project_schedule_changes_record_a_contract_revision_and_require_reason(): void
    {
        $version = $this->publish('time');
        $project = app(CreateClientProjectWithOffering::class)->create($this->attributes(), $version->id, [], null, $this->admin);
        try {
            app(ClientProjectAgreementManager::class)->updateProject($project, ['cycle_anchor_day' => 8], null, $this->admin);
            $this->fail('Schedule changes need a reason.');
        } catch (ValidationException) {
            $this->assertSame(6, $project->fresh()->cycle_anchor_day);
        }
        app(ClientProjectAgreementManager::class)->updateProject($project, ['cycle_anchor_day' => 8], 'تغییر موعد توافق‌شده', $this->admin);
        $this->assertSame(2, $project->planVersions()->count());
        $this->assertSame(8, $project->planVersions()->orderByDesc('version')->first()->agreement_snapshot['project_schedule']['cycle_anchor_day']);
        $this->actingAs($this->admin);
        Livewire::test(EditClientProject::class, ['record' => $project->id])
            ->fillForm(['cycle_anchor_day' => 10, 'contract_schedule_reason' => 'اصلاح موعد در مدیریت'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(3, $project->planVersions()->count());
        $this->assertSame(10, $project->fresh()->cycle_anchor_day);
    }

    public function test_fixed_period_package_preserves_project_dates_and_snapshot(): void
    {
        $draft = $this->draft();
        $draft->update(['schedule_mode' => 'fixed_period', 'billing_interval' => 'one_time', 'scheduling_defaults' => []]);
        $version = app(ServiceOfferingPublisher::class)->publish($draft, $this->admin);
        $attributes = [...$this->attributes(), 'schedule_mode' => 'fixed_period', 'start_date' => '2026-10-11', 'end_date' => '2026-11-11'];
        $project = app(CreateClientProjectWithOffering::class)->create($attributes, $version->id, [], null, $this->admin);
        $this->assertTrue($project->isFixedPeriod());
        $this->assertSame('2026-11-11', $project->planVersions()->first()->agreement_snapshot['project_schedule']['end_date']);
        $this->assertSame(1, $project->cycles()->count());
    }

    public function test_admin_can_manage_catalog_and_publish_draft_using_filament(): void
    {
        $draft = $this->draft();
        $this->actingAs($this->admin);
        Livewire::test(CreateServiceOffering::class)
            ->fillForm(['service_id' => $draft->offering->service_id, 'name' => 'بسته دوم', 'code' => 'SECOND', 'status' => 'active', 'sort_order' => 2])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame(2, $draft->offering->service->offerings()->count());
        Livewire::test(ManageDeliverableTypes::class)->assertOk();
        Livewire::test(ViewServiceOffering::class, ['record' => $draft->offering->id])->assertOk();
        Livewire::test(EditServiceOfferingVersion::class, ['record' => $draft->id])
            ->fillForm(['amount' => '555.1234'])->call('save')->assertHasNoFormErrors();
        Livewire::test(ViewServiceOfferingVersion::class, ['record' => $draft->id])->callAction('publish')->assertHasNoActionErrors();
        $this->assertTrue($draft->fresh()->isPublished());
        $this->assertSame('555.1234', $draft->fresh()->amount);
        Livewire::test(EditServiceOfferingVersion::class, ['record' => $draft->id])->assertForbidden();
    }

    public function test_filament_project_creation_selects_package_and_displays_snapshot(): void
    {
        $version = $this->publish();
        $customer = Customer::factory()->create();
        $this->actingAs($this->admin);
        Livewire::test(CreateClientProject::class)
            ->fillForm(['offering_version_id' => $version->id])
            ->assertFormSet(['schedule_mode' => 'recurring', 'cycle_anchor_day' => 6, 'monthly_limit_hours' => 1])
            ->fillForm(['customer_id' => $customer->id, 'title' => 'پروژه بسته', 'status' => 'active'])
            ->call('create')->assertHasNoFormErrors();
        $project = ClientProject::where('title', 'پروژه بسته')->firstOrFail();
        $this->assertSame('tasks', $project->delivery_mode);
        $this->assertSame(1, $project->planVersions()->count());
        Livewire::test(ViewClientProject::class, ['record' => $project->id])
            ->assertOk()->assertSee('بسته عمومی')->assertSee('شرایط اولیه')->assertSee('تعهد عمومی');
    }

    public function test_filament_customer_overrides_and_revision_action_preserve_prior_agreement(): void
    {
        $version = $this->publish();
        $this->actingAs($this->admin);
        Livewire::test(CreateClientProject::class)
            ->fillForm(['offering_version_id' => $version->id])
            ->fillForm([
                'customer_id' => Customer::factory()->create()->id,
                'title' => 'قرارداد اختصاصی',
                'status' => 'active',
                'contract_override' => true,
                'contract_overrides.amount' => '15.1250',
                'contract_override_reason' => 'تخفیف توافق‌شده',
            ])->call('create')->assertHasNoFormErrors();
        $project = ClientProject::where('title', 'قرارداد اختصاصی')->firstOrFail();
        $first = $project->planVersions()->first();
        $this->assertSame('15.1250', $first->agreement_snapshot['amount']);
        Livewire::test(ViewClientProject::class, ['record' => $project->id])
            ->mountAction('reviseAgreement')
            ->setActionData([
                'offering_version_id' => $version->id,
                'effective_from' => $project->cycles()->first()->ends_at->toDateString(),
                'overrides.amount' => '18.2500',
                'contract_override_reason' => 'توافق جدید',
            ])
            ->callMountedAction()->assertHasNoActionErrors();
        $this->assertSame(2, $project->planVersions()->count());
        $this->assertSame('15.1250', $first->fresh()->agreement_snapshot['amount']);
        $this->assertSame('18.2500', $project->planVersions()->orderByDesc('version')->first()->agreement_snapshot['amount']);
    }

    public function test_float_amounts_and_zero_deliverables_are_rejected(): void
    {
        $draft = $this->draft();
        try {
            $draft->update(['amount' => 0.1]);
            $this->fail('Float financial input must be rejected.');
        } catch (ValidationException) {
            $this->assertSame('12345678901234.1234', $draft->fresh()->amount);
        }
        $this->expectException(ValidationException::class);
        $draft->deliverables()->first()->update(['quantity' => '0']);
    }

    public function test_additive_migration_preserves_legacy_project_activity_and_invoice_data(): void
    {
        $project = ClientProject::factory()->create();
        $activity = ClientProjectActivity::factory()->for($project, 'project')->create();
        $invoice = Invoice::create([
            'customer_id' => $project->customer_id, 'invoice_number' => 'LEGACY-1',
            'period_start' => '2026-10-01', 'period_end' => '2026-10-31',
            'currency' => 'IRT', 'subtotal' => '42.50', 'total_amount' => '42.50', 'status' => 'draft',
        ]);
        $item = $invoice->items()->create([
            'client_project_activity_id' => $activity->id, 'activity_title_snapshot' => 'عنوان تاریخی',
            'unit_price' => '42.5000', 'total_amount' => '42.50', 'currency' => 'IRT',
        ]);
        $before = [$project->fresh()->getAttributes(), $activity->fresh()->getAttributes(), $invoice->fresh()->getAttributes(), $item->fresh()->getAttributes()];
        // RefreshDatabase is isolated to SQLite :memory: by the repository's safety provider.
        $migration = require database_path('migrations/2026_10_11_100000_create_service_offering_contract_foundation.php');
        $deliveryMigration = require database_path('migrations/2026_10_11_200000_add_task_delivery_cycles_and_work_items.php');
        $deliveryMigration->down();
        $migration->down();
        $migration->up();
        $deliveryMigration->up();
        foreach ([$project, $activity, $invoice, $item] as $index => $record) {
            $after = $record->fresh()->getAttributes();
            ksort($before[$index]);
            ksort($after);
            $this->assertSame($before[$index], $after);
        }
        $this->assertDatabaseCount('service_offerings', 0);
        $this->assertSame('time', $project->fresh()->delivery_mode);
    }
}
