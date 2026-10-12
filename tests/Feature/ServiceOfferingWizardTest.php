<?php

namespace Tests\Feature;

use App\Filament\Resources\DeliverableTypeResource;
use App\Filament\Resources\DeliverableTypeResource\Pages\ManageDeliverableTypes;
use App\Filament\Resources\ServiceOfferingResource;
use App\Filament\Resources\ServiceOfferingResource\Pages\CreateServiceOffering;
use App\Filament\Resources\ServiceOfferingResource\Pages\ListServiceOfferings;
use App\Filament\Resources\ServiceOfferingResource\Pages\ManageServiceOffering;
use App\Filament\Resources\ServiceOfferingResource\Pages\ViewServiceOffering;
use App\Filament\Resources\ServiceOfferingVersionResource\Pages\EditServiceOfferingVersion;
use App\Models\DeliverableType;
use App\Models\Service;
use App\Models\ServiceOffering;
use App\Models\ServiceOfferingVersion;
use App\Models\User;
use App\Services\ServiceOfferingWizardManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceOfferingWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->service = Service::create(['name' => 'خدمت عمومی', 'slug' => 'wizard-service', 'status' => 'draft']);
        $this->actingAs($this->admin);
    }

    private function data(): array
    {
        return [
            'service_id' => $this->service->id, 'name' => 'بسته ماهانه', 'description' => 'شرایط عمومی', 'sort_order' => 0,
            'delivery_mode' => 'time', 'schedule_mode' => 'recurring', 'billing_basis' => 'package', 'billing_interval' => 'monthly',
            'amount' => '12345678901234.1234', 'currency_code' => 'IRT', 'contract_terms' => 'پشتیبانی ماهانه',
            'scheduling_defaults' => ['cycle_anchor_day' => 1], 'deliverables' => [],
            'features' => [['title' => 'پشتیبانی', 'type' => 'boolean', 'value' => false, 'unit' => null]],
        ];
    }

    public function test_guided_steps_inline_type_and_draft_retries_preserve_form(): void
    {
        $page = Livewire::test(CreateServiceOffering::class)
            ->assertSee('اطلاعات بسته')->assertSee('نحوه ارائه و قیمت')->assertSee('تعهدات تحویل')
            ->assertSee('ویژگی‌ها و شرایط بسته')->assertSee('بررسی و انتشار')->assertSee('اولین نوع تحویل را تعریف کنید')
            ->fillForm([...$this->data(), 'delivery_mode' => 'tasks', 'deliverables' => [
                ['deliverable_type_id' => null, 'name' => 'گزارش', 'quantity' => '2', 'unit' => 'report', 'approval_required' => false, 'revision_allowance' => 0],
            ]])
            ->call('dispatchFormEvent', 'wizard::nextStep', 'data', 0)->assertHasNoFormErrors()->assertDispatched('next-wizard-step');
        $lineKey = array_key_first($page->get('data.deliverables'));
        $page->callFormComponentAction("deliverables.$lineKey.deliverable_type_id", 'createOption', data: ['name' => 'گزارش عمومی', 'unit' => 'report', 'active' => true, 'description' => 'نوع مشترک'])
            ->assertHasNoFormComponentActionErrors();
        $type = DeliverableType::sole();
        $this->assertSame(26, strlen($type->code));
        $this->assertEquals($type->id, $page->get("data.deliverables.$lineKey.deliverable_type_id"));
        $this->assertSame('گزارش عمومی', $page->get("data.deliverables.$lineKey.name"));
        $this->assertSame('بسته ماهانه', $page->get('data.name'));
        $this->assertSame('12345678901234.1234', $page->get('data.amount'));
        $page->call('saveDraft')->assertHasNoFormErrors()->call('saveDraft')->assertHasNoFormErrors();
        $this->assertDatabaseCount('service_offerings', 1);
        $this->assertDatabaseCount('service_offering_versions', 1);
        $this->assertDatabaseCount('service_offering_deliverables', 1);
        $this->assertSame('draft', ServiceOfferingVersion::sole()->status);
        $this->assertFalse(ServiceOfferingVersion::sole()->features[0]['value']);
        $page->call('publish')->assertHasNoFormErrors()->call('publish')->assertHasNoFormErrors();
        $this->assertDatabaseCount('service_offerings', 1);
        $this->assertDatabaseCount('service_offering_versions', 1);
        $this->assertDatabaseCount('service_offering_deliverables', 1);
        $this->assertSame($type->code, ServiceOfferingVersion::sole()->catalog_snapshot['deliverables'][0]['type_snapshot']['code']);
        $this->assertSame('2.0000', ServiceOfferingVersion::sole()->catalog_snapshot['deliverables'][0]['quantity']);
    }

    public function test_failed_publication_is_atomic_and_grouped_errors_keep_input(): void
    {
        $page = Livewire::test(CreateServiceOffering::class)->fillForm([...$this->data(), 'delivery_mode' => 'tasks'])
            ->call('publish')->assertHasFormErrors(['deliverables'])->assertSee('بازگشت به تعهدات تحویل');
        $this->assertSame('بسته ماهانه', $page->get('data.name'));
        $this->assertSame('12345678901234.1234', $page->get('data.amount'));
        $this->assertDatabaseCount('service_offerings', 0);
        $this->assertDatabaseCount('service_offering_versions', 0);
        $page->call('saveDraft')->assertHasNoFormErrors();
        $this->assertDatabaseCount('service_offerings', 1);
    }

    public function test_service_retries_resume_and_new_versions_preserve_published_snapshots(): void
    {
        $manager = app(ServiceOfferingWizardManager::class);
        $editor = $manager->open($this->admin);
        $version = $manager->save($this->admin, $editor['token'], $this->data());
        $offering = $version->offering;
        $resume = $manager->open($this->admin, $offering);
        foreach ($this->data() as $field => $value) {
            $this->assertSame($value, $resume['data'][$field]);
        }
        $published = $manager->save($this->admin, $resume['token'], $resume['data'], true);
        $snapshot = $published->catalog_snapshot;
        $this->assertEquals($published->id, $manager->save($this->admin, $resume['token'], $resume['data'], true)->id);
        $this->assertSame('active', $offering->fresh()->status);
        $this->assertDatabaseCount('service_offering_versions', 1);
        $new = $manager->open($this->admin, $offering);
        $next = $manager->save($this->admin, $new['token'], [...$new['data'], 'amount' => '200', 'features' => []]);
        $this->assertSame(2, $next->version);
        $this->assertSame('draft', $next->status);
        $this->assertSame('200.0000', $next->amount);
        $this->assertSame($snapshot, $published->fresh()->catalog_snapshot);
        $this->assertSame('12345678901234.1234', $published->fresh()->amount);
        $this->assertSame($next->definition()['amount'], $manager->open($this->admin, $offering)['data']['amount']);
        // A repeated request from the editor that created the original draft may
        // not change that draft once someone else has published it.
        try {
            $manager->save($this->admin, $editor['token'], [...$this->data(), 'amount' => '100']);
            $this->fail('A published version was edited.');
        } catch (ValidationException) {
            $this->assertSame($snapshot, $published->fresh()->catalog_snapshot);
        }
    }

    public function test_contextual_workspace_resumes_draft_and_legacy_routes_remain_available(): void
    {
        $offering = ServiceOffering::create(['service_id' => $this->service->id, 'name' => 'بسته قدیمی']);
        $code = $offering->code;
        $this->assertSame('ادامه تکمیل بسته', ServiceOfferingResource::wizardLabel($offering));
        $page = Livewire::test(ManageServiceOffering::class, ['record' => $offering->id])->assertFormSet(['name' => 'بسته قدیمی'])
            ->fillForm($this->data())->call('saveDraft')->assertHasNoFormErrors();
        $version = $offering->versions()->first();
        $this->assertSame('ویرایش و انتشار', ServiceOfferingResource::wizardLabel($offering->fresh()));
        Livewire::test(ManageServiceOffering::class, ['record' => $offering->id])->assertFormSet(['amount' => '12345678901234.1234']);
        Livewire::test(EditServiceOfferingVersion::class, ['record' => $version->id])->assertOk();
        $page->call('publish')->assertHasNoFormErrors();
        $this->assertSame('ایجاد نسخه جدید', ServiceOfferingResource::wizardLabel($offering->fresh()));
        Livewire::test(ViewServiceOffering::class, ['record' => $offering->id])->assertSee('ندارد')->assertActionVisible('wizard');
        Livewire::test(ManageServiceOffering::class, ['record' => $offering->id])->fillForm(['amount' => '300'])->call('saveDraft')->assertHasNoFormErrors();
        $this->assertSame('ادامه ویرایش نسخه جدید', ServiceOfferingResource::wizardLabel($offering->fresh()));
        $offering->update(['status' => 'archived']);
        Livewire::test(ViewServiceOffering::class, ['record' => $offering->id])->assertOk()->assertActionHidden('wizard');
        Livewire::test(ManageServiceOffering::class, ['record' => $offering->id])->assertForbidden();
        $this->assertSame($code, $offering->fresh()->code);
    }

    public function test_navigation_permissions_and_expired_editor_do_not_create_records(): void
    {
        $this->assertFalse(DeliverableTypeResource::shouldRegisterNavigation());
        Livewire::test(ListServiceOfferings::class)->assertActionVisible('deliverableTypes')->assertActionHasLabel('create', 'ایجاد بسته خدمات');
        Livewire::test(ManageDeliverableTypes::class)->assertOk();
        $manager = app(ServiceOfferingWizardManager::class);
        $editor = $manager->open($this->admin);
        Cache::flush();
        try {
            $manager->save($this->admin, $editor['token'], $this->data());
            $this->fail('Expired editor created records.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('service_offerings', 0);
        }
        $user = User::factory()->create();
        $this->actingAs($user);
        Livewire::test(CreateServiceOffering::class)->assertForbidden();
        Livewire::test(ManageDeliverableTypes::class)->assertForbidden();
        try {
            $manager->open($user);
            $this->fail('Non-administrator opened an editor.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('service_offerings', 0);
        }
    }
}
