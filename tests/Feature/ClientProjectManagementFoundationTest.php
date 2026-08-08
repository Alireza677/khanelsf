<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientProjectResource\Pages\CreateClientProject;
use App\Models\ClientProject;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Services\CustomerMembershipManager;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClientProjectManagementFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_project_for_the_correct_customer(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = Customer::factory()->create();
        $this->actingAs($admin);

        Livewire::test(CreateClientProject::class)
            ->fillForm([
                'customer_id' => $customer->id,
                'title' => 'پروژه بهینه‌سازی سایت',
                'type' => 'SEO',
                'status' => ClientProject::STATUS_ACTIVE,
                'progress' => 70,
                'start_date' => '2026-08-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $project = ClientProject::query()->where('title', 'پروژه بهینه‌سازی سایت')->firstOrFail();
        $this->assertTrue($project->customer->is($customer));
    }

    public function test_client_sees_only_projects_for_the_selected_customer(): void
    {
        $client = User::factory()->client()->create();
        $assigned = Customer::factory()->create();
        $other = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($assigned, $client, 'owner');
        ClientProject::factory()->for($assigned)->create(['title' => 'Visible Client Project']);
        ClientProject::factory()->for($other)->create(['title' => 'Hidden Client Project']);

        $this->actingAs($client, 'client')
            ->get(route('client.projects.index', ['customer' => $assigned->id]))
            ->assertOk()
            ->assertSee('Visible Client Project')
            ->assertDontSee('Hidden Client Project');
    }

    public function test_client_cannot_open_another_customers_project(): void
    {
        $client = User::factory()->client()->create();
        $assigned = Customer::factory()->create();
        $other = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($assigned, $client, 'member');
        $foreignProject = ClientProject::factory()->for($other)->create();

        $this->actingAs($client, 'client')
            ->get(route('client.projects.show', [
                'project' => $foreignProject->id,
                'customer' => $assigned->id,
            ]))
            ->assertNotFound();
    }

    public function test_multiple_customer_selector_scopes_each_project_list(): void
    {
        $client = User::factory()->client()->create();
        $first = Customer::factory()->create(['display_name' => 'First Customer']);
        $second = Customer::factory()->create(['display_name' => 'Second Customer']);
        $memberships = app(CustomerMembershipManager::class);
        $memberships->assign($first, $client, 'owner');
        $memberships->assign($second, $client, 'member');
        ClientProject::factory()->for($first)->create(['title' => 'First Project']);
        ClientProject::factory()->for($second)->create(['title' => 'Second Project']);

        $this->actingAs($client, 'client')
            ->get(route('client.projects.index', ['customer' => $second->id]))
            ->assertOk()
            ->assertSee('First Customer')
            ->assertSee('Second Customer')
            ->assertSee('Second Project')
            ->assertDontSee('First Project');
    }

    public function test_archived_customer_projects_fail_closed(): void
    {
        $client = User::factory()->client()->create();
        $customer = Customer::factory()->create(['status' => Customer::STATUS_ARCHIVED]);
        app(CustomerMembershipManager::class)->assign($customer, $client, 'member');
        ClientProject::factory()->for($customer)->create(['title' => 'Archived Project']);

        $this->actingAs($client, 'client')
            ->get(route('client.projects.index'))
            ->assertOk()
            ->assertSee('حساب مشتری در دسترس نیست')
            ->assertDontSee('Archived Project');
    }

    public function test_customer_with_projects_cannot_be_deleted_accidentally(): void
    {
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create();

        try {
            $customer->delete();
            $this->fail('The customer foreign key should restrict deletion.');
        } catch (QueryException) {
            $this->assertDatabaseHas('customers', ['id' => $customer->id]);
            $this->assertDatabaseHas('client_projects', ['id' => $project->id]);
        }
    }

    public function test_public_cms_project_routes_remain_unchanged(): void
    {
        $publicProject = Project::factory()->published()->create(['title' => 'Public Portfolio Project']);

        $this->get(route('projects.index'))->assertOk()->assertSee('Public Portfolio Project');
        $this->get(route('projects.show', $publicProject->slug))->assertOk();
    }
}
