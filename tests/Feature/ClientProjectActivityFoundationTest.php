<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectActivityResource\Pages\CreateClientProjectActivity;
use App\Filament\Resources\ClientProjectResource\Pages\CreateClientProject;
use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\ClientProjectMonthlyTimeService;
use App\Services\CustomerMembershipManager;
use App\Services\DurationFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ClientProjectActivityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_an_integer_minute_activity_for_private_project(): void
    {
        $admin = User::factory()->admin()->create();
        $project = ClientProject::factory()->create();
        $this->actingAs($admin);

        Livewire::test(CreateClientProjectActivity::class)
            ->fillForm([
                'client_project_id' => $project->id,
                'activity_date' => '2026-08-10',
                'title' => 'بهینه‌سازی فنی',
                'duration_hours' => 1,
                'duration_remainder_minutes' => 30,
                'visibility' => ClientProjectActivity::VISIBILITY_CLIENT,
                'status' => ClientProjectActivity::STATUS_PUBLISHED,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('client_project_activities', [
            'client_project_id' => $project->id,
            'duration_minutes' => 90,
            'title' => 'بهینه‌سازی فنی',
        ]);
    }

    public function test_public_cms_project_cannot_be_selected_for_an_activity(): void
    {
        $admin = User::factory()->admin()->create();
        ClientProject::factory()->create();
        Project::factory()->count(2)->create();
        $publicProject = Project::query()->latest('id')->firstOrFail();
        $this->actingAs($admin);

        Livewire::test(CreateClientProjectActivity::class)
            ->fillForm([
                'client_project_id' => $publicProject->id,
                'activity_date' => '2026-08-10',
                'title' => 'Invalid Public Project Work',
                'duration_hours' => 1,
                'duration_remainder_minutes' => 0,
                'visibility' => ClientProjectActivity::VISIBILITY_INTERNAL,
                'status' => ClientProjectActivity::STATUS_DRAFT,
            ])
            ->call('create')
            ->assertHasFormErrors(['client_project_id']);

        $this->assertDatabaseMissing('client_project_activities', ['title' => 'Invalid Public Project Work']);
    }

    public function test_monthly_allocation_is_stored_and_formatted_as_integer_minutes(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($admin);

        Livewire::test(CreateClientProject::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'title' => 'پشتیبانی ماهانه',
                'status' => ClientProject::STATUS_ACTIVE,
                'progress' => 0,
                'monthly_limit_hours' => 10,
                'monthly_limit_remainder_minutes' => 0,
                'has_unlimited_monthly_hours' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = ClientProject::query()->where('title', 'پشتیبانی ماهانه')->firstOrFail();
        $this->assertSame(600, $project->monthly_hour_limit_minutes);
        $this->assertSame('10 ساعت', app(DurationFormatter::class)->format($project->monthly_hour_limit_minutes));
    }

    public function test_monthly_summary_counts_drafts_and_internal_work_but_excludes_cancelled(): void
    {
        $project = ClientProject::factory()->create(['monthly_hour_limit_minutes' => 600]);
        $month = CarbonImmutable::create(2026, 8, 1);

        ClientProjectActivity::factory()->for($project, 'project')->publishedForClient()->create([
            'activity_date' => '2026-08-05', 'duration_minutes' => 180,
        ]);
        ClientProjectActivity::factory()->for($project, 'project')->create([
            'activity_date' => '2026-08-06', 'duration_minutes' => 90,
            'visibility' => ClientProjectActivity::VISIBILITY_INTERNAL,
            'status' => ClientProjectActivity::STATUS_DRAFT,
        ]);
        ClientProjectActivity::factory()->for($project, 'project')->create([
            'activity_date' => '2026-08-07', 'duration_minutes' => 120,
            'status' => ClientProjectActivity::STATUS_CANCELLED,
        ]);

        $summary = app(ClientProjectMonthlyTimeService::class)->summarize($project, $month);

        $this->assertSame(270, $summary['used_minutes']);
        $this->assertSame(330, $summary['remaining_minutes']);
        $this->assertSame(45, $summary['usage_percentage']);
        $this->assertSame(1, $summary['published_client_activity_count']);
        $this->assertSame(2, $summary['admin_activity_count']);
    }

    public function test_client_sees_only_published_client_activities_and_never_internal_notes(): void
    {
        $client = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create(['monthly_hour_limit_minutes' => 600]);
        app(CustomerMembershipManager::class)->assign($customer, $client, 'owner');

        ClientProjectActivity::factory()->for($project, 'project')->publishedForClient()->create([
            'activity_date' => now()->toDateString(),
            'title' => 'Visible Activity',
            'description' => 'Public description',
            'internal_notes' => 'Never reveal this note',
        ]);
        foreach ([
            ['Internal Activity', ClientProjectActivity::VISIBILITY_INTERNAL, ClientProjectActivity::STATUS_PUBLISHED],
            ['Draft Activity', ClientProjectActivity::VISIBILITY_CLIENT, ClientProjectActivity::STATUS_DRAFT],
            ['Cancelled Activity', ClientProjectActivity::VISIBILITY_CLIENT, ClientProjectActivity::STATUS_CANCELLED],
        ] as [$title, $visibility, $status]) {
            ClientProjectActivity::factory()->for($project, 'project')->create(compact('title', 'visibility', 'status'));
        }

        $this->actingAs($client, 'client')
            ->get(route('client.projects.show', ['project' => $project->id, 'customer' => $customer->id]))
            ->assertOk()
            ->assertSee('Visible Activity')
            ->assertSee('Public description')
            ->assertDontSee('Never reveal this note')
            ->assertDontSee('Internal Activity')
            ->assertDontSee('Draft Activity')
            ->assertDontSee('Cancelled Activity');

        $this->assertArrayNotHasKey('internal_notes', ClientProjectActivity::query()->firstOrFail()->toArray());
    }

    public function test_other_customer_activity_cannot_be_exposed_through_project_id(): void
    {
        $client = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($customer, $client, 'member');
        $foreignProject = ClientProject::factory()->for($other)->create();
        ClientProjectActivity::factory()->for($foreignProject, 'project')->publishedForClient()->create(['title' => 'Foreign Work']);

        $this->actingAs($client, 'client')
            ->get(route('client.projects.show', ['project' => $foreignProject->id, 'customer' => $customer->id]))
            ->assertNotFound();
    }

    public function test_duration_and_timestamps_cannot_contradict_each_other(): void
    {
        $this->expectException(ValidationException::class);

        ClientProjectActivity::factory()->create([
            'duration_minutes' => 30,
            'started_at' => '2026-08-10 09:00:00',
            'ended_at' => '2026-08-10 10:00:00',
        ]);
    }

    public function test_invalid_month_input_fails_validation_safely(): void
    {
        $client = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create();
        app(CustomerMembershipManager::class)->assign($customer, $client, 'member');

        $this->actingAs($client, 'client')
            ->get(route('client.projects.show', [
                'project' => $project->id,
                'customer' => $customer->id,
                'month' => 'not-a-month',
            ]))
            ->assertSessionHasErrors('month');
    }

    public function test_deleting_performer_preserves_historical_activity(): void
    {
        $performer = User::factory()->admin()->create();
        $activity = ClientProjectActivity::factory()->create(['performed_by' => $performer->id]);

        $performer->delete();

        $this->assertDatabaseHas('client_project_activities', [
            'id' => $activity->id,
            'performed_by' => null,
        ]);
    }
}
