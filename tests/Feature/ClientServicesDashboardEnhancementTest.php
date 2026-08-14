<?php

namespace Tests\Feature;

use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerMembershipManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientServicesDashboardEnhancementTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_aggregates_project_time_and_handles_overage(): void
    {
        [$user, $customer] = $this->member();
        $first = ClientProject::factory()->for($customer)->create(['title' => 'پروژه اول', 'status' => ClientProject::STATUS_ACTIVE, 'monthly_hour_limit_minutes' => 120]);
        $second = ClientProject::factory()->for($customer)->create(['title' => 'پروژه دوم', 'status' => ClientProject::STATUS_ACTIVE, 'monthly_hour_limit_minutes' => 60]);
        ClientProjectActivity::factory()->for($first, 'project')->create(['duration_minutes' => 150, 'activity_date' => now()]);
        ClientProjectActivity::factory()->for($second, 'project')->publishedForClient()->create(['title' => 'گزارش مجاز', 'duration_minutes' => 60, 'activity_date' => now()]);

        $this->actingAs($user, 'client')->get(route('account.services.index'))
            ->assertOk()
            ->assertSee('3 ساعت و 30 دقیقه')
            ->assertSee('مازاد')
            ->assertSee('30 دقیقه')
            ->assertSee('پروژه‌های فعال')
            ->assertSee('گزارش مجاز');
    }

    public function test_dashboard_uses_no_limit_state_without_a_fake_percentage(): void
    {
        [$user, $customer] = $this->member();
        $project = ClientProject::factory()->for($customer)->create(['monthly_hour_limit_minutes' => null]);
        ClientProjectActivity::factory()->for($project, 'project')->create(['duration_minutes' => 60, 'activity_date' => now()]);

        $this->actingAs($user, 'client')->get(route('account.services.index'))
            ->assertOk()
            ->assertSee('برای مجموعه پروژه‌ها تعیین نشده است.')
            ->assertSee('--usage: 0', false);
    }

    public function test_activity_filters_remain_customer_scoped_and_private_content_never_enters_html(): void
    {
        [$user, $customer] = $this->member();
        $ownProject = ClientProject::factory()->for($customer)->create(['title' => 'پروژه خودی']);
        $foreignProject = ClientProject::factory()->create(['title' => 'پروژه بیگانه']);
        ClientProjectActivity::factory()->for($ownProject, 'project')->publishedForClient()->create(['title' => 'فعالیت خودی', 'internal_notes' => 'یادداشت خصوصی', 'activity_date' => now()]);
        ClientProjectActivity::factory()->for($ownProject, 'project')->create(['title' => 'پیش‌نویس محرمانه', 'activity_date' => now()]);
        ClientProjectActivity::factory()->for($foreignProject, 'project')->publishedForClient()->create(['title' => 'فعالیت بیگانه', 'activity_date' => now()]);

        $this->actingAs($user, 'client')->get(route('account.services.index', [
            'project' => $foreignProject->id,
            'range' => 'all',
        ]))->assertOk()
            ->assertSee('فعالیت خودی')
            ->assertDontSee('فعالیت بیگانه')
            ->assertDontSee('پیش‌نویس محرمانه')
            ->assertDontSee('یادداشت خصوصی');
    }

    private function member(): array
    {
        $user = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        app(CustomerMembershipManager::class)->attach($customer, $user, 'member');

        return [$user, $customer];
    }
}
