<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectActivityResource\Pages\ListClientProjectActivities;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityCreationWizardTest extends TestCase
{
    use RefreshDatabase;

    public function test_wizard_opens_and_validates_each_step_before_navigation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $project = ClientProject::factory()->create();

        Livewire::test(ListClientProjectActivities::class)
            ->assertActionExists('quickCreateActivity')
            ->assertActionExists('create')
            ->mountAction('quickCreateActivity')
            ->assertActionDataSet([
                'visibility' => ClientProjectActivity::VISIBILITY_INTERNAL,
                'activity_status' => ClientProjectActivity::STATUS_DRAFT,
            ])
            ->assertWizardCurrentStep(1, 'mountedActionForm')
            ->goToNextWizardStep('mountedActionForm')
            ->assertHasActionErrors(['client_project_id'])
            ->assertWizardCurrentStep(1, 'mountedActionForm');

        Livewire::test(ListClientProjectActivities::class)
            ->mountAction('quickCreateActivity')
            ->setActionData(['client_project_id' => $project->id])
            ->goToNextWizardStep('mountedActionForm')
            ->assertWizardCurrentStep(2, 'mountedActionForm')
            ->assertSee('filamentJalaliFormComponent', escape: false)
            ->assertSee('x-load-src=', escape: false)
            ->assertDontSee('ax-load-src=', escape: false)
            ->assertSee('shouldCloseOnDateSelection: true', escape: false)
            ->goToNextWizardStep('mountedActionForm')
            ->assertHasActionErrors(['title', 'duration_remainder_minutes'])
            ->assertWizardCurrentStep(2, 'mountedActionForm');

        Livewire::test(ListClientProjectActivities::class)
            ->mountAction('quickCreateActivity')
            ->setActionData(['client_project_id' => $project->id])
            ->goToNextWizardStep('mountedActionForm')
            ->setActionData([
                'title' => 'ثبت سریع روزانه',
                'duration_hours' => 2,
                'duration_remainder_minutes' => 30,
            ])->goToWizardStep(3, 'mountedActionForm')->assertHasNoActionErrors()->assertWizardCurrentStep(3, 'mountedActionForm');
    }

    public function test_wizard_creates_activity_with_safe_defaults_and_integer_minutes(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create();
        $this->actingAs($admin);

        Livewire::test(ListClientProjectActivities::class)
            ->callAction('quickCreateActivity', data: [
                'client_project_id' => $project->id,
                'activity_date' => '2026-08-07',
                'title' => 'جلسه برنامه‌ریزی',
                'duration_hours' => 1,
                'duration_remainder_minutes' => 45,
                'internal_notes' => 'Private wizard note',
                'visibility' => ClientProjectActivity::VISIBILITY_INTERNAL,
                'activity_status' => ClientProjectActivity::STATUS_DRAFT,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('فعالیت ثبت شد');

        $activity = ClientProjectActivity::query()->where('title', 'جلسه برنامه‌ریزی')->firstOrFail();
        $this->assertSame($project->id, $activity->client_project_id);
        $this->assertSame('2026-08-07', $activity->activity_date->toDateString());
        $this->assertSame(105, $activity->duration_minutes);
        $this->assertSame($admin->id, $activity->performed_by);
        $this->assertSame(ClientProjectActivity::VISIBILITY_INTERNAL, $activity->visibility);
        $this->assertSame(ClientProjectActivity::STATUS_DRAFT, $activity->status);
        $this->assertArrayNotHasKey('internal_notes', $activity->toArray());
    }

    public function test_jalali_date_picker_frontend_asset_is_published(): void
    {
        $assetPath = public_path('js/mokhosh/filament-jalali/components/filament-jalali.js');

        $this->assertFileExists($assetPath);
        $this->assertStringContainsString('window.dayjs', file_get_contents($assetPath));
    }
}
