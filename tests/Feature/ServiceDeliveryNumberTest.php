<?php

namespace Tests\Feature;

use App\Filament\Resources\ServiceOfferingResource\Pages\CreateServiceOffering;
use App\Models\Customer;
use App\Models\DeliverableType;
use App\Models\Service;
use App\Models\ServiceOffering;
use App\Models\ServiceOfferingVersion;
use App\Models\User;
use App\Services\ClientCycleReportPublisher;
use App\Services\ClientDeliveryPresenter;
use App\Services\ClientProjectAgreementManager;
use App\Services\ClientProjectWorkItemGenerator;
use App\Services\ClientProjectWorkItemManager;
use App\Services\CreateClientProjectWithOffering;
use App\Services\ServiceAgreementData;
use App\Services\ServiceOfferingPublisher;
use App\Services\ServiceOfferingWizardManager;
use App\Support\ServiceDeliveryNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceDeliveryNumberTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-12 12:00:00'));
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    private function rejects(callable $call): void
    {
        try {
            $call();
            $this->fail('Invalid numeric input was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function draft(string $unit = 'count', string $quantity = '3'): ServiceOfferingVersion
    {
        $service = Service::create(['name' => 'خدمت', 'slug' => 'number-'.uniqid(), 'status' => 'draft']);
        $offering = ServiceOffering::create(['name' => 'بسته', 'service_id' => $service->id, 'status' => 'active']);
        $type = DeliverableType::create(['name' => 'تعهد', 'unit' => $unit]);
        $version = app(ServiceOfferingPublisher::class)->newDraft($offering, $this->admin);
        $version->update(['delivery_mode' => 'tasks', 'amount' => '12345678901234.1234']);
        $version->deliverables()->create(['deliverable_type_id' => $type->id, 'name' => 'تعهد', 'quantity' => $quantity, 'unit' => $unit]);

        return $version;
    }

    private function project(ServiceOfferingVersion $version)
    {
        return app(CreateClientProjectWithOffering::class)->create(['customer_id' => Customer::factory()->create()->id, 'title' => 'قرارداد', 'status' => 'active', 'schedule_mode' => 'recurring', 'cycle_anchor_day' => 6], $version->id, [], null, $this->admin);
    }

    public function test_exact_display_and_integer_decimal_validation_are_independent(): void
    {
        foreach (['3.0000' => '3', '60.0000' => '60', '2.5000' => '2.5', '12.7500' => '12.75', '0.0000' => '0', '-0.0000' => '0', '9007199254740993.00010000' => '9007199254740993.0001'] as $raw => $display) {
            $this->assertSame($display, ServiceDeliveryNumber::format($raw));
        }
        $this->assertNull(ServiceDeliveryNumber::format(null));
        foreach (['count', 'session', ' جلسه ', 'عدد'] as $unit) {
            $this->assertSame('3.0000', ServiceAgreementData::quantity('3.0000', $unit));
            $this->rejects(fn () => ServiceAgreementData::quantity('3.5', $unit));
        }
        foreach (['meter', 'm2', 'مترمربع', 'hour'] as $unit) {
            $this->assertSame('2.5000', ServiceAgreementData::quantity('2.5', $unit));
        }
        foreach (['0', '-1', '1e2'] as $quantity) {
            $this->rejects(fn () => ServiceAgreementData::quantity($quantity, 'count'));
        }
        $this->rejects(fn () => ServiceAgreementData::quantity(2.5, 'meter'));
        $integer = ['title' => 'مدت', 'type' => 'number', 'value' => '60', 'unit' => 'second', 'numeric_format' => 'integer'];
        $this->assertSame([$integer], ServiceAgreementData::features([$integer]));
        $this->rejects(fn () => ServiceAgreementData::features([[...$integer, 'value' => '60.5']]));
        $this->rejects(fn () => ServiceAgreementData::features([[...$integer, 'numeric_format' => 'invalid']]));
        $this->rejects(fn () => ServiceAgreementData::features([[...$integer, 'type' => 'text']]));
        $legacy = ['title' => 'مدت', 'type' => 'number', 'value' => '2.5', 'unit' => 'hour'];
        $this->assertSame([$legacy], ServiceAgreementData::features([$legacy]));
        $this->assertSame(ServiceAgreementData::featuresForComparison([$legacy]), ServiceAgreementData::featuresForComparison([[...$legacy, 'numeric_format' => 'decimal']]));
        $this->assertSame('12345678901234.1234', ServiceAgreementData::decimal('12345678901234.1234', 'amount'));
    }

    public function test_model_work_items_and_wizard_enforce_precision_on_server(): void
    {
        $draft = $this->draft();
        $line = $draft->deliverables()->first();
        $this->rejects(fn () => $line->update(['quantity' => '3.5']));
        $this->assertSame('3.0000', $line->fresh()->quantity);
        $version = app(ServiceOfferingPublisher::class)->publish($draft->fresh(), $this->admin);
        $project = $this->project($version);
        $cycle = $project->cycles()->first();
        $manager = app(ClientProjectWorkItemManager::class);
        $this->rejects(fn () => $manager->createExtra($cycle, ['title' => 'کار', 'quantity' => '3.5', 'unit' => 'session'], $this->admin));
        $this->rejects(fn () => $cycle->workItems()->create(['client_project_id' => $project->id, 'title' => 'کار مستقیم', 'quantity' => '3.5', 'unit' => 'count']));
        $this->rejects(fn () => $cycle->workItems()->create(['client_project_id' => $project->id, 'client_project_cycle_commitment_id' => $cycle->commitments()->first()->id, 'title' => 'کار وابسته', 'quantity' => '3.5', 'unit' => 'count']));
        $work = $manager->createExtra($cycle, ['title' => 'کار قابل تقسیم', 'quantity' => '2.5', 'unit' => 'meter'], $this->admin);
        $this->rejects(fn () => $manager->update($work, ['unit' => 'count'], $work->lock_version, $this->admin));
        $this->assertSame('meter', $work->fresh()->unit);
        $work = $manager->update($work, ['quantity' => '3', 'unit' => 'count'], $work->lock_version, $this->admin);
        $this->assertSame('3.0000', $work->quantity);
        $page = Livewire::test(CreateServiceOffering::class)->fillForm([
            'service_id' => $draft->offering->service_id, 'name' => 'بسته جدید',
            'features' => [['title' => 'مدت', 'type' => 'number', 'numeric_format' => 'integer', 'value' => '60.5', 'unit' => 'second']],
        ])->call('saveDraft')->assertHasFormErrors();
        $key = array_key_first($page->get('data.features'));
        $page->set("data.features.$key.value", '60')->call('saveDraft')->assertHasNoFormErrors();
        $this->assertSame('integer', ServiceOffering::where('name', 'بسته جدید')->first()->versions()->first()->features[0]['numeric_format']);
    }

    public function test_numeric_format_is_snapshotted_and_future_amendments_leave_history_unchanged(): void
    {
        $draft = $this->draft('meter', '2.5');
        $features = [
            ['title' => 'مدت', 'type' => 'number', 'value' => '60', 'unit' => 'second', 'numeric_format' => 'integer'],
            ['title' => 'حضور', 'type' => 'number', 'value' => '2.5', 'unit' => 'hour', 'numeric_format' => 'decimal'],
        ];
        $draft->update(['features' => $features]);
        $version = app(ServiceOfferingPublisher::class)->publish($draft, $this->admin);
        $project = $this->project($version);
        $plan = $project->planVersions()->first();
        $cycle = $project->cycles()->first();
        $snapshot = $cycle->agreement_snapshot;
        $this->assertSame($features, $version->catalog_snapshot['features']);
        $this->assertSame($features, $plan->agreement_snapshot['features']);
        $this->assertSame('2.5000', $cycle->commitments()->first()->quantity);
        $this->rejects(fn () => app(ClientProjectAgreementManager::class)->append($project, $version, ['features' => [[...$features[1], 'numeric_format' => 'integer']]], 'اصلاح', $cycle->ends_at->toDateString(), $this->admin));
        $features[1]['numeric_format'] = 'integer';
        $features[1]['value'] = '3';
        $amendment = app(ClientProjectAgreementManager::class)->append($project, $version, ['features' => $features], 'اصلاح برای دوره آینده', $cycle->ends_at->toDateString(), $this->admin);
        $this->assertSame('integer', $amendment->agreement_snapshot['features'][1]['numeric_format']);
        $this->assertSame('decimal', $plan->fresh()->agreement_snapshot['features'][1]['numeric_format']);
        $this->assertSame($snapshot, $cycle->fresh()->agreement_snapshot);
        $this->assertSame('12345678901234.1234', $version->fresh()->amount);
    }

    public function test_legacy_fractional_count_snapshots_remain_usable_and_reports_are_only_formatted(): void
    {
        $version = app(ServiceOfferingPublisher::class)->publish($this->draft(), $this->admin);
        $project = $this->project($version);
        $plan = $project->planVersions()->first();
        $cycle = $project->cycles()->first();
        $catalog = $version->catalog_snapshot;
        $agreement = $plan->agreement_snapshot;
        $catalog['deliverables'][0]['quantity'] = $agreement['deliverables'][0]['quantity'] = '3.5000';
        // SQLite fixture for an actual pre-validation contract, never a production backfill.
        DB::table('service_offering_versions')->where('id', $version->id)->update(['catalog_snapshot' => json_encode($catalog)]);
        DB::table('service_offering_deliverables')->where('service_offering_version_id', $version->id)->update(['quantity' => '3.5000']);
        DB::table('client_project_plan_versions')->where('id', $plan->id)->update(['catalog_snapshot' => json_encode($catalog), 'agreement_snapshot' => json_encode($agreement)]);
        DB::table('client_project_cycles')->where('id', $cycle->id)->update(['agreement_snapshot' => json_encode($agreement)]);
        DB::table('client_project_cycle_commitments')->where('client_project_cycle_id', $cycle->id)->update(['quantity' => '3.5000', 'definition_snapshot' => json_encode($agreement['deliverables'][0])]);
        $version->refresh();
        $cycle->refresh();
        $this->assertSame('3.5000', $version->definition()['deliverables'][0]['quantity']);
        $this->assertSame(4, app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin));
        $fraction = $cycle->workItems()->where('quantity', '0.5000')->firstOrFail();
        app(ClientProjectWorkItemManager::class)->update($fraction, ['title' => 'کار تاریخی'], $fraction->lock_version, $this->admin);
        app(ClientProjectAgreementManager::class)->append($project, $version, ['amount' => '200'], 'اصلاح مبلغ', $cycle->ends_at->toDateString(), $this->admin);
        $wizard = app(ServiceOfferingWizardManager::class);
        $editor = $wizard->open($this->admin, $version->offering);
        $editor['data']['deliverables'][0]['quantity'] = '4';
        $next = $wizard->save($this->admin, $editor['token'], $editor['data']);
        $this->assertSame('4.0000', $next->deliverables()->first()->quantity);
        $this->travelTo($cycle->ends_at->addDay());
        $report = app(ClientCycleReportPublisher::class)->publish($cycle, $this->admin, 'incomplete');
        $reportSnapshot = $report->snapshot;
        $delivery = ['cycles' => [], 'cycle' => app(ClientDeliveryPresenter::class)->cycle($cycle)];
        $html = view('client.projects.delivery-cycles', ['delivery' => $delivery, 'serviceRoutes' => ['project' => 'account.projects.show'], 'project' => ['id' => $project->id, 'delivery_mode' => 'tasks'], 'portalCustomer' => $project->customer])->render();
        $this->assertStringContainsString('تعهد: 3.5 count', $html);
        $this->assertStringContainsString('باقی‌مانده 3.5', $html);
        $this->assertStringNotContainsString('3.5000', $html);
        $this->assertSame($reportSnapshot, $report->fresh()->snapshot);
        $this->assertSame($agreement, $cycle->fresh()->agreement_snapshot);
        $this->assertSame($catalog, $version->fresh()->catalog_snapshot);
    }
}
