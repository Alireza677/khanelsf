<?php

namespace Tests\Feature;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\InternalLinks\Sources\ServiceInternalLinkSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use App\Enums\ServicePricingMode;
use App\Enums\ServiceUnit;
use App\Filament\Resources\ClientProjectActivityResource;
use App\Filament\Resources\ClientProjectActivityResource\Pages\CreateClientProjectActivity;
use App\Filament\Resources\ClientProjectActivityResource\Pages\ListClientProjectActivities;
use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\ServiceResource\Pages\EditService;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\ClientProjectActivityPresenter;
use App\Services\ServiceActivityCatalog;
use App\Services\ServiceActivityPricingCalculator;
use App\Services\ServiceSettings;
use App\Services\SettingsService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceCommercialActivityIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_service_is_valid_and_operational_availability_is_independent_from_publication(): void
    {
        $legacy = Service::query()->create(['name' => 'قدیمی', 'slug' => 'legacy']);
        $operational = Service::query()->create([
            'name' => 'عملیاتی', 'slug' => 'operational', 'status' => Service::STATUS_DRAFT,
            'available_for_activities' => true,
        ]);

        $this->assertNull($legacy->pricing_mode);
        $this->assertFalse($legacy->available_for_activities);
        $this->assertFalse($operational->isPublished());
        $this->assertTrue(Service::query()->availableForActivities()->whereKey($operational)->exists());
    }

    public function test_pricing_mode_unit_and_custom_label_contracts_are_enforced(): void
    {
        foreach ([
            ['pricing_mode' => 'hourly', 'unit' => 'meter'],
            ['pricing_mode' => 'fixed', 'unit' => 'count'],
            ['pricing_mode' => 'per_unit', 'unit' => 'hour'],
            ['pricing_mode' => 'per_unit', 'unit' => 'custom'],
            ['pricing_mode' => 'per_unit', 'unit' => 'count', 'custom_unit_label' => 'بسته'],
        ] as $index => $invalid) {
            try {
                Service::query()->create(['name' => "Invalid {$index}", 'slug' => "invalid-{$index}", ...$invalid]);
                $this->fail('Expected commercial validation to fail.');
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $hourly = $this->service(['pricing_mode' => 'hourly', 'unit' => 'hour']);
        $fixed = $this->service(['pricing_mode' => 'fixed', 'unit' => 'fixed']);
        $custom = $this->service(['pricing_mode' => 'per_unit', 'unit' => 'custom', 'custom_unit_label' => 'بسته']);

        $this->assertSame(ServiceUnit::Hour, $hourly->unit);
        $this->assertSame(ServicePricingMode::Fixed, $fixed->pricing_mode);
        $this->assertSame('بسته', $custom->custom_unit_label);
    }

    public function test_decimal_safe_calculator_handles_hourly_per_unit_fixed_and_rounding(): void
    {
        $calculator = app(ServiceActivityPricingCalculator::class);

        $this->assertSame('6000000.00', $calculator->calculate('hourly', '1500000', 240, null)['total_amount']);
        $this->assertNull($calculator->calculate('hourly', '1500000', 240, null)['quantity']);
        $this->assertSame('106250000.00', $calculator->calculate('per_unit', '1250000', 600, '85')['total_amount']);
        $this->assertSame('1250000.00', $calculator->calculate('fixed', '1250000', 30, null)['total_amount']);
        $this->assertSame('0.34', $calculator->calculate('per_unit', '0.335', 1, '1')['total_amount']);
    }

    public function test_allowed_units_setting_limits_admin_catalog_without_changing_schema_contract(): void
    {
        app(SettingsService::class)->set('service_allowed_units', json_encode(['hour', 'session']), 'services', 'json');

        $this->assertSame([
            'hour' => 'ساعت',
            'session' => 'جلسه',
        ], app(ServiceSettings::class)->allowedUnitOptions());

        $service = $this->service(['pricing_mode' => 'per_unit', 'unit' => 'meter']);
        $this->assertSame(ServiceUnit::Meter, $service->unit);
    }

    public function test_disabled_global_pricing_keeps_delivery_snapshot_but_does_not_create_money_snapshot(): void
    {
        app(SettingsService::class)->set('service_activity_catalog_enabled', true, 'services', 'boolean');
        app(SettingsService::class)->set('service_pricing_enabled', false, 'services', 'boolean');
        $service = $this->service([
            'available_for_activities' => true, 'pricing_mode' => 'per_unit',
            'unit' => 'count', 'default_unit_price' => '500', 'currency_code' => 'IRT',
        ]);

        $snapshot = ClientProjectActivityResource::applyCommercialFormState([
            'service_id' => $service->id, 'duration_minutes' => 30, 'quantity' => '2',
        ]);

        $this->assertSame('2.0000', $snapshot['quantity']);
        $this->assertNull($snapshot['unit_price_snapshot']);
        $this->assertNull($snapshot['currency_snapshot']);
        $this->assertNull($snapshot['total_amount']);
    }

    public function test_full_form_creates_hourly_activity_with_immutable_snapshot(): void
    {
        $this->enableCatalog();
        $this->actingAs(User::factory()->admin()->create());
        $project = ClientProject::factory()->create();
        $service = $this->service([
            'name' => 'توسعه Laravel', 'available_for_activities' => true,
            'pricing_mode' => 'hourly', 'unit' => 'hour', 'default_unit_price' => '1500000', 'currency_code' => 'IRT',
        ]);

        Livewire::test(CreateClientProjectActivity::class)->fillForm([
            'client_project_id' => $project->id,
            'service_id' => $service->id,
            'activity_date' => '2026-08-14',
            'title' => 'پیاده‌سازی کاربران',
            'duration_hours' => 4,
            'duration_remainder_minutes' => 0,
            'visibility' => 'internal',
            'status' => 'draft',
        ])->call('create')->assertHasNoFormErrors();

        $activity = ClientProjectActivity::query()->firstOrFail();
        $this->assertSame($service->id, $activity->service_id);
        $this->assertSame('توسعه Laravel', $activity->service_name_snapshot);
        $this->assertSame('hour', $activity->service_unit_snapshot);
        $this->assertSame('1500000.0000', $activity->unit_price_snapshot);
        $this->assertNull($activity->quantity);
        $this->assertSame('6000000.00', $activity->total_amount);
    }

    public function test_free_form_activity_remains_valid(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateClientProjectActivity::class)->fillForm([
            'client_project_id' => ClientProject::factory()->create()->id,
            'activity_date' => '2026-08-14', 'title' => 'فعالیت آزاد',
            'duration_hours' => 1, 'duration_remainder_minutes' => 0,
            'visibility' => 'internal', 'status' => 'draft',
        ])->call('create')->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_project_activities', ['service_id' => null, 'title' => 'فعالیت آزاد']);
    }

    public function test_snapshot_does_not_refresh_unless_service_is_explicitly_changed(): void
    {
        $this->enableCatalog();
        $first = $this->service(['name' => 'اول', 'available_for_activities' => true, 'pricing_mode' => 'fixed', 'unit' => 'fixed', 'default_unit_price' => '100']);
        $second = $this->service(['name' => 'دوم', 'available_for_activities' => true, 'pricing_mode' => 'fixed', 'unit' => 'fixed', 'default_unit_price' => '250']);
        $activity = ClientProjectActivity::factory()->create(ClientProjectActivityResource::applyCommercialFormState([
            'service_id' => $first->id, 'duration_minutes' => 60,
        ]));

        $first->update(['name' => 'نام جدید', 'default_unit_price' => '999']);
        $same = ClientProjectActivityResource::applyCommercialFormState([
            'service_id' => $first->id, 'duration_minutes' => 60, 'description' => 'edit',
        ], $activity);
        $activity->update($same);
        $this->assertSame('اول', $activity->fresh()->service_name_snapshot);
        $this->assertSame('100.0000', $activity->fresh()->unit_price_snapshot);

        $changed = ClientProjectActivityResource::applyCommercialFormState([
            'service_id' => $second->id, 'duration_minutes' => 60,
        ], $activity->fresh());
        $activity->update($changed);
        $this->assertSame('دوم', $activity->fresh()->service_name_snapshot);
        $this->assertSame('250.0000', $activity->fresh()->unit_price_snapshot);

        $removed = ClientProjectActivityResource::applyCommercialFormState([
            'service_id' => null, 'duration_minutes' => 60,
        ], $activity->fresh());
        $activity->update($removed);
        $this->assertNull($activity->fresh()->service_name_snapshot);
        $this->assertNull($activity->fresh()->total_amount);
    }

    public function test_deleting_service_nulls_relation_but_keeps_snapshot(): void
    {
        $this->enableCatalog();
        $service = $this->service(['name' => 'حفظ تاریخچه', 'available_for_activities' => true]);
        $activity = ClientProjectActivity::factory()->create(ClientProjectActivityResource::applyCommercialFormState([
            'service_id' => $service->id, 'duration_minutes' => 30,
        ]));

        $service->delete();

        $this->assertNull($activity->fresh()->service_id);
        $this->assertSame('حفظ تاریخچه', $activity->fresh()->service_name_snapshot);

        $activity = $activity->fresh();
        $activity->update(ClientProjectActivityResource::applyCommercialFormState([
            'service_id' => null, 'duration_minutes' => 30, 'description' => 'ویرایش بعد از حذف',
        ], $activity));
        $this->assertSame('حفظ تاریخچه', $activity->fresh()->service_name_snapshot);
    }

    public function test_per_unit_quantity_is_required_and_duration_remains_independent(): void
    {
        $this->enableCatalog();
        $service = $this->service([
            'available_for_activities' => true, 'pricing_mode' => 'per_unit',
            'unit' => 'square_meter', 'default_unit_price' => '1250000',
        ]);

        $data = ClientProjectActivityResource::applyCommercialFormState([
            'service_id' => $service->id, 'duration_minutes' => 600, 'quantity' => '85',
        ]);

        $this->assertSame('85.0000', $data['quantity']);
        $this->assertSame('106250000.00', $data['total_amount']);
        $this->assertSame(600, $data['duration_minutes']);
    }

    public function test_wizard_and_full_form_share_snapshot_calculation(): void
    {
        $this->enableCatalog();
        $this->actingAs(User::factory()->admin()->create());
        $project = ClientProject::factory()->create();
        $service = $this->service([
            'available_for_activities' => true, 'pricing_mode' => 'hourly',
            'unit' => 'hour', 'default_unit_price' => '1000',
        ]);

        Livewire::test(ListClientProjectActivities::class)->callAction('quickCreateActivity', data: [
            'client_project_id' => $project->id, 'service_id' => $service->id,
            'activity_date' => '2026-08-14', 'title' => 'Wizard',
            'duration_hours' => 2, 'duration_remainder_minutes' => 30,
            'visibility' => 'internal', 'activity_status' => 'draft',
        ])->assertHasNoActionErrors();

        $this->assertSame('2500.00', ClientProjectActivity::query()->firstOrFail()->total_amount);
    }

    public function test_client_presentation_does_not_expose_commercial_snapshots(): void
    {
        $activity = ClientProjectActivity::factory()->create([
            'service_name_snapshot' => 'خدمت قابل نمایش',
            'currency_snapshot' => 'IRT',
            'unit_price_snapshot' => '1500000',
            'total_amount' => '6000000',
            'internal_notes' => 'محرمانه',
        ]);

        $presented = app(ClientProjectActivityPresenter::class)->present($activity);

        $this->assertArrayNotHasKey('currency_snapshot', $presented);
        $this->assertArrayNotHasKey('unit_price_snapshot', $presented);
        $this->assertArrayNotHasKey('total_amount', $presented);
        $this->assertArrayNotHasKey('internal_notes', $presented);
    }

    public function test_public_services_toggle_is_fail_closed_but_catalog_and_admin_remain_available(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('public_services_enabled', false, 'services', 'boolean');
        $settings->set('service_activity_catalog_enabled', true, 'services', 'boolean');
        $service = $this->service(['available_for_activities' => true, 'status' => Service::STATUS_ACTIVE]);

        $this->get(route('services.index'))->assertNotFound();
        $this->get(route('services.show', $service->slug))->assertNotFound();
        $locations = app(SitemapService::class)->urls()->pluck('loc');
        $this->assertFalse($locations->contains(route('services.index')));
        $this->assertFalse($locations->contains(route('services.show', $service->slug)));
        $this->assertFalse(app(NavigationSourceRegistry::class)->find('services.archive')?->isAvailable());
        $this->assertFalse(app(ServiceInternalLinkSource::class)->isAvailable());
        $this->assertSame('module_disabled', app(ActionResolver::class)->resolve(
            new ActionDestination('service', $service->id), new ResolutionContext,
        )->reason);
        $this->assertContains($service->id, array_keys(app(ServiceActivityCatalog::class)->options()));

        $this->actingAs(User::factory()->admin()->create());
        $this->get(ServiceResource::getUrl('index'))->assertOk();
    }

    public function test_hidden_service_editor_sections_preserve_existing_data_and_relations(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();
        $service = $this->service([
            'benefits' => [['title' => 'مزیت']],
            'process' => [['title' => 'مرحله']],
            'deliverables' => [['title' => 'خروجی']],
        ]);
        $service->projects()->attach($project);
        foreach (['benefits', 'process', 'deliverables', 'media', 'related_projects'] as $section) {
            app(SettingsService::class)->set("service_form_{$section}_enabled", false, 'services', 'boolean');
        }

        $this->actingAs($admin);
        Livewire::test(EditService::class, ['record' => $service->id])
            ->fillForm(['name' => 'ویرایش امن'])
            ->call('save')->assertHasNoFormErrors();

        $service->refresh();
        $this->assertSame('مزیت', $service->benefits[0]['title']);
        $this->assertSame('مرحله', $service->process[0]['title']);
        $this->assertSame('خروجی', $service->deliverables[0]['title']);
        $this->assertTrue($service->projects()->whereKey($project)->exists());

        app(SettingsService::class)->set('service_form_benefits_enabled', true, 'services', 'boolean');
        $this->assertSame('مزیت', $service->fresh()->benefits[0]['title']);
    }

    private function enableCatalog(): void
    {
        app(SettingsService::class)->set('service_activity_catalog_enabled', true, 'services', 'boolean');
        app(SettingsService::class)->set('service_pricing_enabled', true, 'services', 'boolean');
    }

    private function service(array $overrides = []): Service
    {
        $id = str()->uuid()->toString();

        return Service::query()->create([
            'name' => 'Service '.$id,
            'slug' => 'service-'.$id,
            'status' => Service::STATUS_DRAFT,
            ...$overrides,
        ]);
    }
}
