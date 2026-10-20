<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectActivityResource\Pages\CreateClientProjectActivity;
use App\Filament\Resources\ClientProjectActivityResource\Pages\ListClientProjectActivities;
use App\Filament\Resources\ClientProjectResource\Pages\ViewClientProject;
use App\Models\ClientProject;
use App\Models\Customer;
use App\Models\DeliverableType;
use App\Models\Service;
use App\Models\ServiceOffering;
use App\Models\User;
use App\Services\ClientProjectDeliveryProgress;
use App\Services\ClientProjectWorkItemGenerator;
use App\Services\ClientProjectWorkItemManager;
use App\Services\ClientServicesDashboardPresenter;
use App\Services\ClientWorkItemDeliveryManager;
use App\Services\CreateClientProjectWithOffering;
use App\Services\CustomerMembershipManager;
use App\Services\ServiceOfferingPublisher;
use App\Services\WorkRegistrationContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class WorkRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00'));
        $this->admin = User::factory()->admin()->create();
        Storage::fake('service_deliveries');
        $this->actingAs($this->admin);
    }

    private function project(string $mode = 'tasks', bool $approval = true): ClientProject
    {
        $service = Service::create(['name' => 'خدمت ویدئو', 'slug' => uniqid('video-'), 'status' => 'draft']);
        $offering = ServiceOffering::create(['service_id' => $service->id, 'name' => 'بسته قراردادی', 'code' => uniqid(), 'status' => 'active']);
        $draft = app(ServiceOfferingPublisher::class)->newDraft($offering, $this->admin);
        $draft->update(['delivery_mode' => $mode, 'amount' => '900000', 'features' => [['title' => 'ویژگی قراردادی محفوظ', 'type' => 'boolean', 'value' => true]]]);
        foreach ([['ویدئوی نهایی', 'count', 3], ['فیلم‌برداری', 'session', 2]] as [$name, $unit, $quantity]) {
            $type = DeliverableType::create(['name' => $name, 'code' => uniqid(), 'unit' => $unit]);
            $draft->deliverables()->create(['deliverable_type_id' => $type->id, 'name' => $name, 'unit' => $unit, 'quantity' => $quantity, 'approval_required' => $approval]);
        }
        $version = app(ServiceOfferingPublisher::class)->publish($draft, $this->admin);

        return app(CreateClientProjectWithOffering::class)->create([
            'customer_id' => Customer::factory()->create()->id, 'title' => 'پروژه '.uniqid(), 'status' => 'active',
            'schedule_mode' => 'recurring', 'cycle_anchor_day' => 6, 'monthly_hour_limit_minutes' => $mode === 'hybrid' ? 120 : null,
        ], $version->id, [], null, $this->admin);
    }

    private function item(ClientProject $project)
    {
        $cycle = $project->cycles()->firstOrFail();
        app(ClientProjectWorkItemGenerator::class)->generate($cycle, $this->admin);
        $item = $cycle->workItems()->firstOrFail();
        $manager = app(ClientProjectWorkItemManager::class);
        $item = $manager->update($item, ['visibility' => 'client', 'status' => 'ready'], $item->lock_version, $this->admin);

        return $manager->update($item, ['status' => 'in_progress'], $item->lock_version, $this->admin);
    }

    public function test_project_wizard_delivers_tasks_and_rejects_work_from_another_project(): void
    {
        $project = $this->project();
        $item = $this->item($project);
        $foreign = $this->item($this->project());

        Livewire::test(ViewClientProject::class, ['record' => $project->getRouteKey()])
            ->mountAction('quickCreateActivity')
            ->assertActionDataSet(['client_project_id' => $project->id, 'operation' => 'work_item', 'work_cycle_id' => $item->client_project_cycle_id])
            ->setActionData(['task_action' => 'deliver', 'work_item_id' => $foreign->id, 'work_data' => ['lock_version' => $foreign->lock_version]])
            ->callMountedAction()->assertHasActionErrors(['work_item_id']);
        $this->assertSame(0, $foreign->deliveries()->count());

        Livewire::test(ViewClientProject::class, ['record' => $project->getRouteKey()])
            ->callAction('quickCreateActivity', data: [
                'task_action' => 'deliver', 'work_item_id' => $item->id,
                'work_data' => ['lock_version' => $item->lock_version, 'message' => 'تحویل از صفحه پروژه',
                    'client_files' => [UploadedFile::fake()->createWithContent('output.txt', 'project output')]],
            ])->assertHasNoActionErrors()->assertNotified('نسخه تحویل ثبت شد');

        $this->assertSame('submitted', $item->fresh()->status->value);
        $this->assertNull($item->fresh()->accepted_at);
        $this->assertSame(1, $item->deliveries()->count());
        $this->assertDatabaseCount('client_project_activities', 0);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_project_wizard_preserves_both_hybrid_operations(): void
    {
        $project = $this->project('hybrid');
        Livewire::test(ViewClientProject::class, ['record' => $project->getRouteKey()])
            ->callAction('quickCreateActivity', data: [
                'operation' => 'time', 'title' => 'زمان ترکیبی در پروژه', 'duration_hours' => 0, 'duration_remainder_minutes' => 15,
            ])->assertHasNoActionErrors();
        Livewire::test(ViewClientProject::class, ['record' => $project->getRouteKey()])
            ->callAction('quickCreateActivity', data: [
                'operation' => 'work_item', 'task_action' => 'extra',
                'work_data' => ['title' => 'کار ترکیبی اضافی', 'quantity' => 1, 'unit' => 'count', 'visibility' => 'internal'],
            ])->assertHasNoActionErrors();

        $this->assertSame(15, $project->activities()->sole()->duration_minutes);
        $this->assertSame('کار ترکیبی اضافی', $project->workItems()->sole()->title);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_task_wizard_delivers_without_duration_and_waits_for_authorized_customer(): void
    {
        $project = $this->project();
        $item = $this->item($project);
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: [
            'client_project_id' => $project->id, 'work_cycle_id' => $item->client_project_cycle_id,
            'task_action' => 'deliver', 'work_item_id' => $item->id,
            'work_data' => ['lock_version' => $item->lock_version, 'message' => 'ویدئو آماده بررسی است',
                'client_files' => [UploadedFile::fake()->createWithContent('output.txt', 'delivered output')]],
        ])->assertHasNoActionErrors()->assertNotified('نسخه تحویل ثبت شد');
        $this->assertDatabaseCount('client_project_activities', 0);
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame('submitted', $item->fresh()->status->value);
        $file = $item->deliveries()->firstOrFail()->files()->firstOrFail();
        Storage::disk('service_deliveries')->assertExists($file->path);
        $this->assertNull($item->fresh()->accepted_at);
        $this->assertSame('0.0000', app(ClientProjectDeliveryProgress::class)->commitment($item->commitment)['completed']);
        $owner = User::factory()->client()->create();
        app(CustomerMembershipManager::class)->attach($project->customer, $owner, 'owner');
        app(ClientWorkItemDeliveryManager::class)->review($item->deliveries()->firstOrFail(), $owner, $item->fresh()->lock_version, 'accepted', null);
        $this->assertSame('1.0000', app(ClientProjectDeliveryProgress::class)->commitment($item->commitment)['completed']);
        $item = app(ClientProjectWorkItemManager::class)->update($item->fresh(), ['status' => 'completed'], $item->fresh()->lock_version, $this->admin);
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: [
            'client_project_id' => $project->id, 'work_cycle_id' => $item->client_project_cycle_id,
            'task_action' => 'deliver', 'work_item_id' => $item->id, 'work_data' => ['lock_version' => $item->lock_version],
        ])->assertHasActionErrors(['work_item_id']);
        $this->assertSame(1, $item->deliveries()->count());
    }

    public function test_task_wizard_uses_existing_edit_form_and_rejects_duplicate_or_stale_delivery(): void
    {
        $project = $this->project();
        $item = $this->item($project);
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: [
            'client_project_id' => $project->id, 'work_cycle_id' => $item->client_project_cycle_id,
            'task_action' => 'update', 'work_item_id' => $item->id,
            'work_data' => ['title' => 'عنوان اصلاح‌شده', 'visibility' => 'client', 'status' => 'in_progress', 'lock_version' => $item->lock_version],
        ])->assertHasNoActionErrors();
        $this->assertSame('عنوان اصلاح‌شده', $item->fresh()->title);
        $this->assertSame('count', $item->fresh()->unit);
        $data = ['client_project_id' => $project->id, 'work_cycle_id' => $item->client_project_cycle_id, 'task_action' => 'deliver', 'work_item_id' => $item->id,
            'work_data' => ['lock_version' => $item->lock_version, 'message' => 'stale']];
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: $data)->assertHasActionErrors(['work_item_id']);
        app(ClientWorkItemDeliveryManager::class)->submit($item->fresh(), $this->admin, $item->fresh()->lock_version, 'first');
        $data['work_data']['lock_version'] = $item->fresh()->lock_version;
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: $data)->assertHasActionErrors(['work_item_id']);
        $this->assertSame(1, $item->deliveries()->count());
    }

    public function test_work_item_selection_is_scoped_to_project_and_current_cycle(): void
    {
        $project = $this->project();
        $foreign = $this->item($this->project());
        $this->assertNull(app(WorkRegistrationContext::class)->item($project->id, $foreign->id));
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: [
            'client_project_id' => $project->id, 'work_cycle_id' => $project->cycles()->first()->id,
            'task_action' => 'deliver', 'work_item_id' => $foreign->id, 'work_data' => ['lock_version' => $foreign->lock_version],
        ])->assertHasActionErrors();
        $this->assertSame(0, $foreign->deliveries()->count());
    }

    public function test_task_step_has_no_time_fields_and_generation_preserves_existing_work(): void
    {
        $project = $this->project();
        Livewire::test(ListClientProjectActivities::class)->mountAction('quickCreateActivity')
            ->setActionData(['client_project_id' => $project->id])->goToNextWizardStep('mountedActionForm')
            ->assertWizardCurrentStep(2, 'mountedActionForm')->assertSee('قرارداد و تعهدات دوره')
            ->assertSee('ایجاد کارهای قراردادیِ ثبت‌نشده')->assertDontSee('عنوان فعالیت');
        $cycle = $project->cycles()->firstOrFail();
        $generator = app(ClientProjectWorkItemGenerator::class);
        $this->assertSame(5, $generator->generate($cycle, $this->admin));
        $before = $cycle->workItems()->get()->map->getAttributes()->all();
        $this->assertSame(0, $generator->generate($cycle, $this->admin));
        $this->assertSame($before, $cycle->workItems()->get()->map->getAttributes()->all());
    }

    public function test_hybrid_offers_separate_operations_and_task_project_cannot_record_time(): void
    {
        $project = $this->project('hybrid');
        $this->assertSame(['time', 'work_item'], array_keys(app(WorkRegistrationContext::class)->choices($project->id)));
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: [
            'client_project_id' => $project->id, 'operation' => 'time', 'title' => 'فعالیت ترکیبی',
            'activity_date' => today()->toDateString(), 'duration_hours' => 0, 'duration_remainder_minutes' => 15,
        ])->assertHasNoActionErrors();
        $this->assertDatabaseHas('client_project_activities', ['client_project_id' => $project->id, 'duration_minutes' => 15]);
        $tasks = $this->project();
        $this->expectException(ValidationException::class);
        app(WorkRegistrationContext::class)->operation($tasks->id, 'time');
    }

    public function test_extra_work_does_not_fulfill_or_charge_the_package(): void
    {
        $project = $this->project();
        $cycle = $project->cycles()->firstOrFail();
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: [
            'client_project_id' => $project->id, 'work_cycle_id' => $cycle->id, 'task_action' => 'extra',
            'work_data' => ['title' => 'کار خارج از بسته', 'quantity' => 1, 'unit' => 'count', 'visibility' => 'internal', 'status' => 'planned'],
        ])->assertHasNoActionErrors();
        $this->assertNull($cycle->workItems()->firstOrFail()->client_project_cycle_commitment_id);
        $this->assertSame('3.0000', app(ClientProjectDeliveryProgress::class)->cycle($cycle)[0]['remaining']);
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_delivery_without_customer_approval_can_be_completed_through_existing_transition(): void
    {
        $project = $this->project(approval: false);
        $item = $this->item($project);
        app(ClientWorkItemDeliveryManager::class)->submit($item, $this->admin, $item->lock_version, 'output');
        $item->refresh();
        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: [
            'client_project_id' => $project->id, 'work_cycle_id' => $item->client_project_cycle_id,
            'task_action' => 'update', 'work_item_id' => $item->id,
            'work_data' => ['title' => $item->title, 'visibility' => 'client', 'status' => 'completed', 'lock_version' => $item->lock_version],
        ])->assertHasNoActionErrors();
        $this->assertSame('completed', $item->fresh()->status->value);
        $this->assertSame('1.0000', app(ClientProjectDeliveryProgress::class)->commitment($item->commitment)['completed']);
    }

    public function test_full_activity_form_cannot_create_fake_task_activity(): void
    {
        $project = $this->project();
        Livewire::test(CreateClientProjectActivity::class)->fillForm([
            'client_project_id' => $project->id, 'title' => 'کار زمانی ساختگی', 'activity_date' => today()->toDateString(),
            'duration_hours' => 1, 'duration_remainder_minutes' => 0,
        ])->call('create')->assertHasFormErrors(['client_project_id']);
        $this->assertDatabaseCount('client_project_activities', 0);
    }

    public function test_portal_cards_use_snapshot_and_independent_units_and_customer_isolation(): void
    {
        $project = $this->project();
        $foreign = $this->project();
        $user = User::factory()->client()->create();
        app(CustomerMembershipManager::class)->attach($project->customer, $user, 'owner');
        $project->currentPlan->offeringVersion->offering->update(['name' => 'نام عمومی جدید']);
        foreach (['account.services.index', 'account.projects.index'] as $route) {
            $this->actingAs($user, 'client')->get(route($route))->assertOk()
                ->assertSee('بسته قراردادی')->assertSee('ویدئوی نهایی')->assertSee('فیلم‌برداری')
                ->assertSee('۳ عدد')->assertSee('۲ جلسه')->assertSee('portal-project-card', false)
                ->assertDontSee('نام عمومی جدید')->assertDontSee('ویژگی قراردادی محفوظ')
                ->assertDontSee($foreign->title)->assertDontSee('مصرف دوره جاری')->assertDontSee('role="table"', false);
        }
        $card = app(ClientServicesDashboardPresenter::class)->projectCard($project, CarbonImmutable::today());
        $this->assertNull($card['limit_time']);
        $this->assertCount(2, $card['commitments']);
        $this->actingAs($user, 'client')->get(route('account.projects.show', $foreign))->assertNotFound();
        $this->actingAs($user, 'client')->get(route('account.projects.show', $project))->assertOk()->assertSee('ویژگی قراردادی محفوظ');
    }
}
