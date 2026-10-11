<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectCycleResource\Pages\ManageDeliveryCycles;
use App\Filament\Resources\ClientProjectWorkItemResource\Pages\ManageWorkItems;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\ClientProjectWorkItem;
use App\Models\Customer;
use App\Models\DeliverableType;
use App\Models\Service;
use App\Models\ServiceOffering;
use App\Models\User;
use App\Services\ClientCycleReportPublisher;
use App\Services\ClientProjectWorkItemGenerator;
use App\Services\ClientProjectWorkItemManager;
use App\Services\ClientServicesDashboardPresenter;
use App\Services\ClientWorkItemDeliveryManager;
use App\Services\CreateClientProjectWithOffering;
use App\Services\CustomerMembershipManager;
use App\Services\ServiceOfferingPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ClientServiceDeliveryPortalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private User $member;

    private ClientProject $project;

    private ClientProjectWorkItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-11 12:00:00'));
        Storage::fake('service_deliveries');
        $this->admin = User::factory()->admin()->create();
        $service = Service::create(['name' => 'خدمت عمومی', 'slug' => 'portal-delivery', 'status' => 'draft']);
        $offering = ServiceOffering::create(['service_id' => $service->id, 'name' => 'بسته تاریخی', 'code' => 'PORTAL', 'status' => 'active']);
        $type = DeliverableType::create(['name' => 'خروجی عمومی', 'code' => 'OUTPUT', 'unit' => 'report']);
        $draft = app(ServiceOfferingPublisher::class)->newDraft($offering, $this->admin);
        $draft->update(['delivery_mode' => 'tasks', 'amount' => '12345678901234.1234', 'contract_terms' => 'شرایط تاریخی']);
        $draft->deliverables()->create(['deliverable_type_id' => $type->id, 'name' => 'تعهد محفوظ', 'quantity' => '1', 'unit' => 'report', 'approval_required' => true, 'revision_allowance' => 2]);
        $version = app(ServiceOfferingPublisher::class)->publish($draft, $this->admin);
        $this->project = app(CreateClientProjectWithOffering::class)->create(['customer_id' => Customer::factory()->create()->id, 'title' => 'پروژه تحویل', 'status' => 'active', 'schedule_mode' => 'recurring', 'cycle_anchor_day' => 6], $version->id, [], null, $this->admin);
        $this->owner = User::factory()->client()->create();
        $this->member = User::factory()->client()->create();
        app(CustomerMembershipManager::class)->attach($this->project->customer, $this->owner, 'owner');
        app(CustomerMembershipManager::class)->attach($this->project->customer, $this->member, 'member');
        app(ClientProjectWorkItemGenerator::class)->generate($this->project->cycles()->firstOrFail(), $this->admin);
        $this->item = $this->project->workItems()->firstOrFail();
        $this->item = app(ClientProjectWorkItemManager::class)->update($this->item, ['visibility' => 'client', 'status' => 'ready'], 0, $this->admin);
        $this->transition('in_progress');
    }

    private function transition(string $status): void
    {
        $this->item = app(ClientProjectWorkItemManager::class)->update($this->item->fresh(), ['status' => $status], $this->item->fresh()->lock_version, $this->admin);
    }

    private function submit(array $files = [])
    {
        return app(ClientWorkItemDeliveryManager::class)->submit($this->item->fresh(), $this->admin, $this->item->fresh()->lock_version, 'خروجی قابل نمایش', $files);
    }

    private function url(string $action, array $extra = []): string
    {
        return route('account.delivery.'.$action, ['project' => $this->project->id, 'workItem' => $this->item->id, 'customer' => $this->project->customer_id, ...$extra]);
    }

    public function test_members_read_but_only_current_owners_can_review(): void
    {
        $delivery = $this->submit();
        $this->actingAs($this->member, 'client')->get($this->url('show'))->assertOk()->assertSee('خروجی قابل نمایش')->assertDontSee('تأیید این نسخه');
        $this->assertTrue(Gate::forUser($this->member)->allows('view', $this->item));
        $this->assertFalse(Gate::forUser($this->member)->allows('update', $this->item));
        $data = ['lock_version' => $this->item->fresh()->lock_version, 'decision' => 'accepted'];
        $this->actingAs($this->member, 'client')->post($this->url('review', ['delivery' => $delivery->id]), $data)->assertForbidden();
        $this->actingAs($this->owner, 'client')->post($this->url('review', ['delivery' => $delivery->id]), $data)->assertRedirect();
        $this->assertSame('accepted', $this->item->fresh()->status->value);
        $this->assertNotNull($this->item->fresh()->accepted_at);
        $this->assertSame('fulfilled', $this->item->cycle->fresh()->delivery_status);
        $this->transition('completed');
        $this->assertNotNull($this->item->fresh()->completed_at);
    }

    public function test_revision_requires_reason_and_new_submission_preserves_old_review_and_files(): void
    {
        $first = $this->submit(['client' => [UploadedFile::fake()->createWithContent('first.txt', 'first output')]]);
        $version = $this->item->fresh()->lock_version;
        $this->actingAs($this->owner, 'client')->post($this->url('review', ['delivery' => $first->id]), ['lock_version' => $version, 'decision' => 'revision_requested'])->assertSessionHasErrors('reason');
        app(ClientWorkItemDeliveryManager::class)->review($first, $this->owner, $version, 'revision_requested', 'اصلاح محتوا');
        $before = $first->fresh()->getAttributes();
        $this->transition('in_progress');
        $second = $this->submit();
        $this->assertSame(2, $second->version);
        $this->assertSame($before, $first->fresh()->getAttributes());
        $this->assertSame('revision_requested', $first->fresh()->review_state);
        $this->assertSame('اصلاح محتوا', $first->fresh()->review->reason);
        Storage::disk('service_deliveries')->assertExists($first->files()->first()->path);
        app(ClientWorkItemDeliveryManager::class)->review($second, $this->owner, $this->item->fresh()->lock_version, 'accepted', null);
        $this->assertDatabaseCount('client_work_item_reviews', 2);
    }

    public function test_stale_duplicate_and_superseded_reviews_are_rejected_without_mutation(): void
    {
        $first = $this->submit();
        $version = $this->item->fresh()->lock_version;
        foreach ([$version - 1, $version + 1] as $stale) {
            try {
                app(ClientWorkItemDeliveryManager::class)->review($first, $this->owner, $stale, 'accepted', null);
                $this->fail('Stale review accepted.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('client_work_item_reviews', 0);
            }
        }
        app(ClientWorkItemDeliveryManager::class)->review($first, $this->owner, $version, 'revision_requested', 'اصلاح');
        $this->transition('in_progress');
        $second = $this->submit();
        try {
            app(ClientWorkItemDeliveryManager::class)->review($first, $this->owner, $this->item->fresh()->lock_version, 'accepted', null);
            $this->fail('Old review accepted.');
        } catch (ValidationException) {
            $this->assertSame('submitted', $this->item->fresh()->status->value);
        }
        app(ClientWorkItemDeliveryManager::class)->review($second, $this->owner, $this->item->fresh()->lock_version, 'accepted', null);
        try {
            app(ClientWorkItemDeliveryManager::class)->review($second, $this->owner, $this->item->fresh()->lock_version, 'revision_requested', 'تصمیم هم‌زمان');
            $this->fail('Conflicting review accepted.');
        } catch (ValidationException) {
            $this->assertSame('accepted', $second->fresh()->review_state);
        }
        $this->assertDatabaseCount('client_work_item_reviews', 2);
    }

    public function test_private_files_are_scoped_to_membership_item_delivery_and_visibility(): void
    {
        $delivery = $this->submit(['client' => [UploadedFile::fake()->createWithContent('public.txt', 'authorized output')], 'internal' => [UploadedFile::fake()->createWithContent('secret.txt', 'internal secret')]]);
        $clientFile = $delivery->files()->where('visibility', 'client')->first();
        $privateFile = $delivery->files()->where('visibility', 'internal')->first();
        $this->actingAs($this->member, 'client')->get($this->url('show'))->assertOk()->assertSee('public.txt')->assertDontSee('secret.txt')->assertDontSee($clientFile->path);
        $url = $this->url('download', ['delivery' => $delivery->id, 'file' => $clientFile->id]);
        $this->get($url)->assertOk()->assertDownload('public.txt')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($this->url('download', ['delivery' => $delivery->id, 'file' => $privateFile->id]))->assertNotFound();
        $other = ClientProject::factory()->for($this->project->customer)->create();
        $this->get(route('account.delivery.download', ['project' => $other->id, 'workItem' => $this->item->id, 'delivery' => $delivery->id, 'file' => $clientFile->id]))->assertNotFound();
        $foreignCustomer = Customer::factory()->create();
        app(CustomerMembershipManager::class)->attach($foreignCustomer, $this->member, 'member');
        $this->get(route('account.delivery.show', ['project' => $this->project->id, 'workItem' => $this->item->id, 'customer' => $foreignCustomer->id]))->assertNotFound();
        app(CustomerMembershipManager::class)->remove($this->project->customer, $this->member);
        $this->get($url)->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.service-delivery-files.download', $privateFile))->assertOk()->assertDownload('secret.txt');
    }

    public function test_owner_role_revocation_is_checked_even_with_hydrated_objects(): void
    {
        $delivery = $this->submit();
        $this->owner->load('customers');
        app(CustomerMembershipManager::class)->assign($this->project->customer, $this->owner, 'member');
        $this->expectException(AuthorizationException::class);
        app(ClientWorkItemDeliveryManager::class)->review($delivery, $this->owner, $this->item->fresh()->lock_version, 'accepted', null);
    }

    public function test_unapproved_internal_work_and_internal_events_never_leak_into_portal(): void
    {
        $extra = app(ClientProjectWorkItemManager::class)->createExtra($this->item->cycle, ['title' => 'کار داخلی محرمانه', 'description' => 'توضیح داخلی', 'visibility' => 'internal'], $this->admin);
        app(ClientProjectWorkItemManager::class)->update($this->item->fresh(), ['description' => 'توضیح عمومی', 'reason' => 'یادداشت مدیریتی محرمانه', 'due_at' => now()->addDay()], $this->item->fresh()->lock_version, $this->admin);
        $this->actingAs($this->member, 'client')->get(route('account.projects.show', $this->project))->assertOk()->assertSee('تعهد محفوظ')->assertSee('12345678901234.1234')->assertSee('شرایط تاریخی')->assertDontSee('کار داخلی محرمانه')->assertDontSee('یادداشت مدیریتی محرمانه')->assertDontSee('مصرف دوره جاری');
        $this->get(route('account.delivery.show', ['project' => $this->project->id, 'workItem' => $extra->id]))->assertNotFound();
        $this->get($this->url('show'))->assertOk()->assertDontSee('یادداشت مدیریتی محرمانه')->assertSee('در حال اجرا')->assertSee('برنامه کار ثبت شد');
        $this->get(route('account.services.index'))->assertOk()->assertSee('تعهدات تحویل')->assertSee('تعهد محفوظ');
    }

    public function test_reports_preserve_cycle_snapshots_reject_false_success_and_create_immutable_corrections(): void
    {
        $cycle = $this->item->cycle;
        $this->travelTo($cycle->ends_at->addDay()->toImmutable());
        try {
            app(ClientCycleReportPublisher::class)->publish($cycle, $this->admin, 'successful');
            $this->fail('Incomplete report marked successful.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('client_cycle_reports', 0);
        }
        $first = app(ClientCycleReportPublisher::class)->publish($cycle, $this->admin, 'incomplete', 'گزارش اولیه');
        $before = $first->fresh()->getAttributes();
        $delivery = $this->submit();
        app(ClientWorkItemDeliveryManager::class)->review($delivery, $this->owner, $this->item->fresh()->lock_version, 'accepted', null);
        $this->project->currentPlan->offeringVersion->offering->update(['name' => 'نام جدید کاتالوگ']);
        $second = app(ClientCycleReportPublisher::class)->publish($cycle, $this->admin, 'successful', 'اصلاح گزارش');
        $this->assertSame(2, $second->version);
        $this->assertSame($before, $first->fresh()->getAttributes());
        $this->assertSame('1.0000', $first->snapshot['commitments'][0]['remaining']);
        $this->assertSame('0.0000', $second->snapshot['commitments'][0]['remaining']);
        $this->assertSame('12345678901234.1234', $second->snapshot['agreement']['amount']);
        $this->assertSame('بسته تاریخی', $second->snapshot['agreement']['offering']['name']);
        $this->actingAs($this->member, 'client')->get(route('account.projects.show', ['project' => $this->project->id, 'cycle' => $cycle->id]))->assertOk()->assertSee('گزارش اولیه')->assertSee('اصلاح گزارش')->assertDontSee('نام جدید کاتالوگ');
        $this->expectException(ValidationException::class);
        $first->update(['snapshot' => []]);
    }

    public function test_reports_never_include_internal_items_or_file_metadata(): void
    {
        app(ClientProjectWorkItemManager::class)->createExtra($this->item->cycle, ['title' => 'INTERNAL-SECRET'], $this->admin);
        $delivery = $this->submit(['internal' => [UploadedFile::fake()->createWithContent('SECRET.txt', 'private')]]);
        $this->travelTo($this->item->cycle->ends_at->addDay()->toImmutable());
        $report = app(ClientCycleReportPublisher::class)->publish($this->item->cycle, $this->admin, 'incomplete');
        $json = json_encode($report->snapshot);
        $this->assertStringNotContainsString('INTERNAL-SECRET', $json);
        $this->assertStringNotContainsString('SECRET.txt', $json);
        $this->assertStringNotContainsString($delivery->files()->first()->path, $json);
    }

    public function test_files_and_delivery_content_are_immutable_and_invalid_uploads_do_not_create_versions(): void
    {
        try {
            $this->submit(['client' => [UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')]]);
            $this->fail('Executable upload accepted.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('client_work_item_deliveries', 0);
        }
        $delivery = $this->submit();
        try {
            $delivery->update(['message' => 'overwrite']);
            $this->fail('Delivery mutated.');
        } catch (ValidationException) {
            $this->assertSame('خروجی قابل نمایش', $delivery->fresh()->message);
        }
        $this->expectException(ValidationException::class);
        $delivery->delete();
    }

    public function test_existing_filament_resources_submit_versions_show_reviews_and_publish_reports(): void
    {
        $this->actingAs($this->admin);
        $page = ManageWorkItems::class;
        Livewire::test($page)->callTableAction('submitDelivery', $this->item, data: ['message' => 'ارسال از مدیریت', 'lock_version' => $this->item->lock_version,
            'client_files' => [UploadedFile::fake()->createWithContent('admin-upload.txt', 'admin output')]])->assertHasNoTableActionErrors();
        $file = $this->item->deliveries()->firstOrFail()->files()->firstOrFail();
        Storage::disk('service_deliveries')->assertExists($file->path);
        Livewire::test($page)->mountTableAction('view', $this->item)->assertSee('ارسال از مدیریت')->assertSee('نسخه‌های تحویل')->assertSee('منتظر مشتری');
        $cycle = $this->item->cycle;
        $this->travelTo($cycle->ends_at->addDay()->toImmutable());
        $cyclesPage = ManageDeliveryCycles::class;
        Livewire::test($cyclesPage)->callTableAction('publishReport', $cycle, data: ['outcome' => 'incomplete', 'message' => 'گزارش مدیریت'])->assertHasNoTableActionErrors();
        Livewire::test($cyclesPage)->mountTableAction('view', $cycle)->assertSee('گزارش مدیریت')->assertSee('گزارش‌های تاریخی منتشرشده');
    }

    public function test_cancelled_work_remains_incomplete_and_revision_limit_is_enforced(): void
    {
        for ($version = 1; $version <= 2; $version++) {
            $delivery = $this->submit();
            app(ClientWorkItemDeliveryManager::class)->review($delivery, $this->owner, $this->item->fresh()->lock_version, 'revision_requested', 'اصلاح '.$version);
            $this->transition('in_progress');
        }
        $delivery = $this->submit();
        try {
            app(ClientWorkItemDeliveryManager::class)->review($delivery, $this->owner, $this->item->fresh()->lock_version, 'revision_requested', 'اصلاح اضافی');
            $this->fail('Revision allowance exceeded.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('client_work_item_reviews', 2);
        }
        app(ClientWorkItemDeliveryManager::class)->review($delivery, $this->owner, $this->item->fresh()->lock_version, 'accepted', null);
        $this->assertSame(3, $this->item->deliveries()->count());
        $extra = app(ClientProjectWorkItemManager::class)->createExtra($this->item->cycle, ['title' => 'کار لغوشده عمومی', 'visibility' => 'client'], $this->admin);
        app(ClientProjectWorkItemManager::class)->update($extra, ['status' => 'cancelled', 'reason' => 'دلیل لغو داخلی'], 0, $this->admin);
        $this->travelTo($this->item->cycle->ends_at->addDay()->toImmutable());
        $report = app(ClientCycleReportPublisher::class)->publish($this->item->cycle, $this->admin, 'successful');
        $this->assertSame('cancelled', collect($report->snapshot['items'])->firstWhere('id', $extra->id)['status']);
        $this->assertStringNotContainsString('دلیل لغو داخلی', json_encode($report->snapshot, JSON_UNESCAPED_UNICODE));
    }

    public function test_phase_three_migration_round_trip_preserves_hourly_data_and_event_history(): void
    {
        $legacy = ClientProject::factory()->create(['status' => 'active', 'monthly_hour_limit_minutes' => 60]);
        $activity = ClientProjectActivity::factory()->for($legacy, 'project')->create(['activity_date' => today(), 'duration_minutes' => 71]);
        $before = $activity->fresh()->getAttributes();
        $events = $this->item->events()->get()->map->getAttributes()->all();
        $migration = require database_path('migrations/2026_10_11_300000_add_private_deliveries_reviews_and_cycle_reports.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, $activity->fresh()->getAttributes());
        $after = $this->item->events()->get()->map->getAttributes()->all();
        foreach ($events as $index => $event) {
            unset($event['visibility']);
            unset($event['kind']);
            $this->assertSame('internal', $after[$index]['visibility']);
            $this->assertSame('internal', $after[$index]['kind']);
            unset($after[$index]['visibility']);
            unset($after[$index]['kind']);
            $this->assertSame($event, $after[$index]);
        }
        $this->assertSame('time', $legacy->fresh()->delivery_mode);
    }

    public function test_hybrid_portal_separates_unlimited_hours_from_contractual_delivery(): void
    {
        $offering = $this->project->currentPlan->offeringVersion->offering;
        $draft = app(ServiceOfferingPublisher::class)->newDraft($offering, $this->admin);
        $draft->update(['delivery_mode' => 'hybrid']);
        $version = app(ServiceOfferingPublisher::class)->publish($draft, $this->admin);
        $project = app(CreateClientProjectWithOffering::class)->create(['customer_id' => $this->project->customer_id, 'title' => 'پروژه ترکیبی', 'status' => 'active', 'schedule_mode' => 'recurring', 'cycle_anchor_day' => 6, 'monthly_hour_limit_minutes' => null], $version->id, [], null, $this->admin);
        $cycle = $project->cycles()->firstOrFail();
        ClientProjectActivity::factory()->for($project, 'project')->publishedForClient()->create(['client_project_cycle_id' => $cycle->id, 'activity_date' => today(), 'duration_minutes' => 71]);
        $this->actingAs($this->member, 'client')->get(route('account.projects.show', $project))->assertOk()->assertSee('تعهد محفوظ')->assertSee('بدون سقف زمانی')->assertSee('1 ساعت و 11 دقیقه')->assertDontSee('مصرف سهمیه');
        $data = app(ClientServicesDashboardPresenter::class)->present(collect([$project]), now()->toImmutable());
        $this->assertNull($data['projects'][0]['limit_minutes']);
        $this->assertNull($data['current_cycles']['limit_minutes']);
        $this->assertNull($data['current_cycles']['overage_time']);
        $this->assertSame(71, $data['current_cycles']['used_minutes']);
    }
}
