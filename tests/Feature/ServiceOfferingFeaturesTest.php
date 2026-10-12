<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectResource\Pages\ViewClientProject;
use App\Filament\Resources\ServiceOfferingVersionResource\Pages\EditServiceOfferingVersion;
use App\Filament\Resources\ServiceOfferingVersionResource\Pages\ViewServiceOfferingVersion;
use App\Models\ClientProject;
use App\Models\Customer;
use App\Models\DeliverableType;
use App\Models\Service;
use App\Models\ServiceOffering;
use App\Models\ServiceOfferingVersion;
use App\Models\User;
use App\Services\ClientProjectAgreementManager;
use App\Services\ClientProjectCycleResolver;
use App\Services\ClientProjectWorkItemGenerator;
use App\Services\CreateClientProjectWithOffering;
use App\Services\ServiceAgreementData;
use App\Services\ServiceOfferingPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

class ServiceOfferingFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00'));
        $this->admin = User::factory()->admin()->create();
    }

    private function features(): array
    {
        return [
            ['title' => 'پشتیبانی', 'type' => 'boolean', 'value' => false, 'unit' => null],
            ['title' => 'بازه حضور', 'type' => 'text', 'value' => '۳ تا ۴ ساعت', 'unit' => null],
            ['title' => 'مدت پاسخ', 'type' => 'number', 'value' => '60', 'unit' => 'دقیقه'],
            ['title' => 'حد اختیاری', 'type' => 'number', 'value' => '0', 'unit' => null],
        ];
    }

    private function draft(string $mode = 'tasks'): ServiceOfferingVersion
    {
        $service = Service::create(['name' => 'خدمت عمومی', 'slug' => 'features-service', 'status' => 'draft']);
        $offering = ServiceOffering::create(['service_id' => $service->id, 'name' => 'بسته عمومی', 'status' => 'active']);
        $type = DeliverableType::create(['name' => 'گزارش', 'unit' => 'report']);
        $draft = app(ServiceOfferingPublisher::class)->newDraft($offering, $this->admin);
        $draft->update(['delivery_mode' => $mode, 'features' => $this->features(), 'amount' => '12345678901234.1234']);
        $draft->deliverables()->create(['deliverable_type_id' => $type->id, 'name' => 'تعهد قابل شمارش', 'quantity' => '2', 'unit' => 'report', 'approval_required' => false, 'revision_allowance' => 0]);

        return $draft;
    }

    private function project(ServiceOfferingVersion $version, array $overrides = [], ?string $reason = null): ClientProject
    {
        return app(CreateClientProjectWithOffering::class)->create(['customer_id' => Customer::factory()->create()->id, 'title' => 'قرارداد ویژگی‌ها', 'status' => 'active', 'schedule_mode' => 'recurring', 'cycle_anchor_day' => 6, 'monthly_hour_limit_minutes' => $version->delivery_mode === 'time' ? 60 : null], $version->id, $overrides, $reason, $this->admin);
    }

    public function test_feature_types_are_validated_without_loosening_quantities(): void
    {
        $this->assertSame($this->features(), ServiceAgreementData::features($this->features()));
        $this->assertSame([], ServiceAgreementData::features(null));
        $this->assertSame('-60.125', ServiceAgreementData::features([['title' => 'اندازه', 'type' => 'number', 'value' => '-060.1250']])[0]['value']);
        foreach ([
            [['title' => 'نامعتبر', 'type' => 'boolean', 'value' => 'false']],
            [['title' => 'نامعتبر', 'type' => 'text', 'value' => 60]],
            [['title' => 'نامعتبر', 'type' => 'number', 'value' => '۳ تا ۴ ساعت']],
            [['title' => 'نامعتبر', 'type' => 'number', 'value' => 60.25]],
            [['title' => 'نامعتبر', 'type' => 'boolean', 'value' => false, 'unit' => 'ساعت']],
            [['title' => 'نامعتبر', 'type' => 'other', 'value' => 'متن']],
            [['title' => 'نامعتبر', 'type' => 'text', 'value' => 'متن', 'quantity' => '1']],
        ] as $invalid) {
            try {
                ServiceAgreementData::features($invalid);
                $this->fail('Invalid feature accepted.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
        $definition = $this->draft()->definition();
        $this->assertSame('2.0000', $definition['deliverables'][0]['quantity']);
        $this->expectException(ValidationException::class);
        ServiceAgreementData::canonical([...$definition, 'deliverables' => [[...$definition['deliverables'][0], 'quantity' => '۳ تا ۴ ساعت']]]);
    }

    public function test_filament_edits_typed_features_and_published_versions_are_immutable(): void
    {
        $draft = $this->draft();
        $features = $this->features();
        $features[1]['value'] = 'شرایط عمومی جدید';
        foreach ($features as &$feature) {
            if ($feature['type'] === 'number') {
                $feature['numeric_format'] = 'decimal';
            }
        }
        unset($feature);
        $this->actingAs($this->admin);
        Livewire::test(EditServiceOfferingVersion::class, ['record' => $draft->id])->fillForm(['features' => $features])->call('save')->assertHasNoFormErrors();
        $this->assertSame($features, $draft->fresh()->features);
        $published = app(ServiceOfferingPublisher::class)->publish($draft->fresh(), $this->admin);
        $this->assertSame(2, $published->catalog_snapshot['schema_version']);
        $this->assertSame($features, $published->catalog_snapshot['features']);
        Livewire::test(ViewServiceOfferingVersion::class, ['record' => $published->id])->assertSee('ویژگی‌ها و شرایط پکیج')->assertSee('شرایط عمومی جدید')->assertSee('ندارد')->assertSee('دقیقه');
        $copy = app(ServiceOfferingPublisher::class)->newDraft($published->offering, $this->admin);
        $this->assertSame($features, $copy->features);
        $copy->update(['features' => []]);
        $this->assertSame($features, $published->fresh()->features);
        $this->expectException(LogicException::class);
        $draft->update(['features' => []]); // stale draft must not bypass publication lock.
    }

    public function test_contract_overrides_and_future_cycles_preserve_feature_and_work_history(): void
    {
        $published = app(ServiceOfferingPublisher::class)->publish($this->draft(), $this->admin);
        $features = $this->features();
        $features[0]['value'] = true;
        $project = $this->project($published, ['features' => $features], 'پشتیبانی اختصاصی');
        $plan = $project->planVersions()->firstOrFail();
        $cycle = $project->cycles()->firstOrFail();
        $this->assertSame($this->features(), $plan->catalog_snapshot['features']);
        $this->assertSame($features, $plan->agreement_snapshot['features']);
        $this->assertSame($features, $cycle->agreement_snapshot['features']);
        $this->assertSame(1, $cycle->commitments()->count());
        $this->assertSame(2, app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin));
        $planBefore = $plan->getAttributes();
        $cycleBefore = $cycle->fresh()->getAttributes();
        $changed = $features;
        $changed[1]['value'] = 'شرایط دوره آینده';
        try {
            app(ClientProjectAgreementManager::class)->append($project, $published, ['features' => $changed], 'اصلاح', today()->toDateString(), $this->admin);
            $this->fail('Feature amendment entered current cycle.');
        } catch (ValidationException) {
            $this->assertSame(1, $project->planVersions()->count());
        }
        $this->actingAs($this->admin);
        Livewire::test(ViewClientProject::class, ['record' => $project->id])->callAction('reviseAgreement', data: [
            'offering_version_id' => $published->id, 'effective_from' => $cycle->ends_at->toDateString(),
            'overrides' => [...$plan->agreement_snapshot, 'features' => $changed], 'contract_override_reason' => 'توافق دوره آینده',
        ])->assertHasNoActionErrors();
        $next = app(ClientProjectCycleResolver::class)->resolveForDate($project, $cycle->ends_at);
        $this->assertSame($changed, $next->agreement_snapshot['features']);
        $this->assertSame($planBefore, $plan->fresh()->getAttributes());
        $this->assertSame($cycleBefore, $cycle->fresh()->getAttributes());
        $this->assertSame(2, $cycle->workItems()->count());
        $this->assertSame('2.0000', $next->commitments()->first()->quantity);
        $this->assertSame('12345678901234.1234', $next->agreement_snapshot['amount']);
        Livewire::test(ViewClientProject::class, ['record' => $project->id])->assertSee('شرایط دوره آینده')->assertSee('ویژگی‌ها و شرایط پکیج');
    }

    public function test_time_feature_amendments_use_future_boundaries_without_changing_hourly_terms(): void
    {
        $published = app(ServiceOfferingPublisher::class)->publish($this->draft('time'), $this->admin);
        $customFeatures = $this->features();
        $customFeatures[1]['value'] = 'شرایط اختصاصی';
        $project = $this->project($published, ['features' => $customFeatures], 'شرایط اختصاصی مشتری');
        $cycle = $project->cycles()->firstOrFail();
        $features = $customFeatures;
        $features[0]['value'] = true;
        try {
            app(ClientProjectAgreementManager::class)->append($project, $published, ['features' => $features], 'اصلاح ویژگی', today()->toDateString(), $this->admin);
            $this->fail('Hourly features changed current period.');
        } catch (ValidationException) {
            $this->assertSame(1, $project->planVersions()->count());
        }
        app(ClientProjectAgreementManager::class)->append($project, $published, ['amount' => '10'], 'اصلاح مبلغ با رفتار قبلی', today()->toDateString(), $this->admin);
        $this->assertSame(2, $project->planVersions()->count());
        app(ClientProjectAgreementManager::class)->append($project, $published, ['features' => $features], 'ویژگی آینده', $cycle->ends_at->toDateString(), $this->admin);
        $this->assertSame(60, $cycle->fresh()->allocated_minutes);
        $this->assertSame($customFeatures, $project->fresh()->currentPlan->agreement_snapshot['features']);
        $this->assertDatabaseCount('client_project_work_items', 0);
        $this->travelTo($cycle->ends_at->toImmutable());
        $this->assertSame($features, $project->fresh()->currentPlan->agreement_snapshot['features']);
    }

    public function test_legacy_snapshots_and_additive_migration_do_not_backfill_or_mutate_history(): void
    {
        $published = app(ServiceOfferingPublisher::class)->publish($this->draft('time'), $this->admin);
        $legacy = $published->catalog_snapshot;
        unset($legacy['features']);
        $legacy['schema_version'] = 1;
        // Fixture representing an already-published pre-feature version, not a production backfill.
        DB::table('service_offering_versions')->where('id', $published->id)->update(['features' => null, 'catalog_snapshot' => json_encode($legacy, JSON_UNESCAPED_UNICODE)]);
        $published->refresh();
        $before = $published->getAttributes();
        $project = $this->project($published);
        $plan = $project->planVersions()->first();
        $this->assertSame($legacy, $plan->catalog_snapshot);
        $this->assertSame([], $plan->agreement_snapshot['features']);
        $this->assertSame(2, $plan->agreement_snapshot['schema_version']);
        $this->assertSame($before, $published->fresh()->getAttributes());
        $migration = require database_path('migrations/2026_10_11_400000_add_features_to_service_offering_versions.php');
        $migration->down();
        $migration->up();
        $after = $published->fresh()->getAttributes();
        unset($before['features'], $after['features']);
        $this->assertSame($before, $after);
        $this->assertSame($legacy, $plan->fresh()->catalog_snapshot);
        $this->assertSame([], app(ServiceOfferingPublisher::class)->newDraft($published->offering, $this->admin)->features);
    }
}
