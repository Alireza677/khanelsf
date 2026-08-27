<?php

namespace Tests\Feature;

use App\Models\ClientProject;
use App\Models\ClientProjectActivity;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerMembershipManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccountServiceConvergenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_services_dashboard_renders_clickable_project_rows_and_timeline_states_without_removed_boxes(): void
    {
        CarbonImmutable::setTestNow('2026-08-27 12:00:00');

        try {
            $user = User::factory()->client()->create();
            $customer = Customer::factory()->create();
            app(CustomerMembershipManager::class)->assign($customer, $user, 'member');

            $running = ClientProject::factory()->for($customer)->create([
                'title' => 'پروژه در حال اجرا',
                'start_date' => '2026-08-01',
                'end_date' => '2026-09-30',
                'monthly_hour_limit_minutes' => 600,
            ]);
            ClientProject::factory()->for($customer)->create([
                'title' => 'پروژه آینده',
                'start_date' => '2026-10-01',
                'end_date' => '2026-12-01',
            ]);
            ClientProject::factory()->for($customer)->create([
                'title' => 'پروژه دیرکرد',
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-01',
            ]);
            ClientProject::factory()->for($customer)->create([
                'title' => 'پروژه تکمیل‌شده',
                'status' => ClientProject::STATUS_COMPLETED,
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-01',
            ]);

            $this->actingAs($user, 'client')
                ->get(route('account.services.index', ['customer' => $customer->id]))
                ->assertOk()
                ->assertSee('سقف خدمات هر ماه')
                ->assertSee('وضعیت پرداخت')
                ->assertSee('بدون صورتحساب')
                ->assertSee('در حال اجرا')
                ->assertSee('هنوز شروع نشده')
                ->assertSee('از موعد گذشته')
                ->assertSee('تکمیل‌شده')
                ->assertSee(route('account.projects.show', ['project' => $running, 'customer' => $customer->id]), false)
                ->assertDontSee('services-customer-card', false)
                ->assertDontSee('services-quick-links', false)
                ->assertDontSee('نمای کلی');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_service_capability_requires_an_active_customer_membership(): void
    {
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')->get('/account')->assertOk()->assertDontSee('خدمات و پروژه‌های من');
        $this->actingAs($user, 'client')->get('/account/services')->assertForbidden();
        $this->actingAs($user, 'client')->get('/account/projects')->assertForbidden();

        $inactive = Customer::factory()->create(['status' => Customer::STATUS_INACTIVE]);
        app(CustomerMembershipManager::class)->assign($inactive, $user, 'member');

        $this->actingAs($user, 'client')->get('/account')->assertOk()->assertDontSee('خدمات و پروژه‌های من');
        $this->actingAs($user, 'client')->get('/account/services')->assertForbidden();
    }

    public function test_member_can_use_canonical_service_routes_and_only_sees_own_customers(): void
    {
        $user = User::factory()->client()->create();
        $first = Customer::factory()->create(['display_name' => 'مشتری مجاز اول']);
        $second = Customer::factory()->create(['display_name' => 'مشتری مجاز دوم']);
        $foreign = Customer::factory()->create(['display_name' => 'مشتری بیگانه']);
        $memberships = app(CustomerMembershipManager::class);
        $memberships->assign($first, $user, 'owner');
        $memberships->assign($second, $user, 'member');

        $this->actingAs($user, 'client')
            ->get(route('account.services.index', ['customer' => $second->id]))
            ->assertOk()
            ->assertSee('خدمات و پروژه‌های من')
            ->assertSee('مشتری مجاز اول')
            ->assertSee('مشتری مجاز دوم')
            ->assertDontSee('مشتری بیگانه')
            ->assertSee(route('account.projects.index', ['customer' => $second->id]), false);

        $this->actingAs($user, 'client')
            ->get(route('account.services.index', ['customer' => $foreign->id]))
            ->assertForbidden();
    }

    public function test_canonical_projects_are_scoped_to_the_selected_customer(): void
    {
        $user = User::factory()->client()->create();
        $allowed = Customer::factory()->create();
        $foreign = Customer::factory()->create();
        app(CustomerMembershipManager::class)->assign($allowed, $user, 'member');
        $ownProject = ClientProject::factory()->for($allowed)->create(['title' => 'پروژه مجاز']);
        $foreignProject = ClientProject::factory()->for($foreign)->create(['title' => 'پروژه محرمانه']);

        $this->actingAs($user, 'client')
            ->get(route('account.projects.index', ['customer' => $allowed->id]))
            ->assertOk()
            ->assertSee('پروژه مجاز')
            ->assertDontSee('پروژه محرمانه');

        $this->actingAs($user, 'client')
            ->get(route('account.projects.show', ['project' => $ownProject, 'customer' => $allowed->id]))
            ->assertOk();

        $this->actingAs($user, 'client')
            ->get(route('account.projects.show', ['project' => $foreignProject, 'customer' => $allowed->id]))
            ->assertNotFound();
    }

    public function test_canonical_activity_views_preserve_client_privacy_and_time_contract(): void
    {
        $user = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create(['monthly_hour_limit_minutes' => 600]);
        app(CustomerMembershipManager::class)->assign($customer, $user, 'owner');

        ClientProjectActivity::factory()->for($project, 'project')->publishedForClient()->create([
            'title' => 'فعالیت نمایشی',
            'internal_notes' => 'یادداشت کاملاً داخلی',
            'duration_minutes' => 60,
        ]);
        ClientProjectActivity::factory()->for($project, 'project')->create([
            'title' => 'فعالیت داخلی',
            'visibility' => ClientProjectActivity::VISIBILITY_INTERNAL,
            'status' => ClientProjectActivity::STATUS_PUBLISHED,
            'duration_minutes' => 120,
        ]);
        ClientProjectActivity::factory()->for($project, 'project')->create([
            'title' => 'فعالیت پیش‌نویس',
            'visibility' => ClientProjectActivity::VISIBILITY_CLIENT,
            'status' => ClientProjectActivity::STATUS_DRAFT,
            'duration_minutes' => 90,
        ]);

        $this->actingAs($user, 'client')
            ->get(route('account.projects.show', ['project' => $project, 'customer' => $customer->id]))
            ->assertOk()
            ->assertSee('فعالیت نمایشی')
            ->assertSee('زمان مصرف‌شده پروژه')
            ->assertSee('4 ساعت و 30 دقیقه')
            ->assertDontSee('یادداشت کاملاً داخلی')
            ->assertDontSee('فعالیت داخلی')
            ->assertDontSee('فعالیت پیش‌نویس');
    }

    public function test_web_admin_session_does_not_authenticate_account_services(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'web')
            ->get('/account/projects')
            ->assertRedirect(route('login'));
    }

    public function test_legacy_dashboard_and_project_routes_remain_functional(): void
    {
        $user = User::factory()->client()->create();
        $customer = Customer::factory()->create();
        $project = ClientProject::factory()->for($customer)->create();
        app(CustomerMembershipManager::class)->assign($customer, $user, 'member');

        $this->actingAs($user, 'client')->get('/dashboard?customer='.$customer->id)->assertOk();
        $this->actingAs($user, 'client')->get('/dashboard/projects?customer='.$customer->id)->assertOk();
        $this->actingAs($user, 'client')
            ->get('/dashboard/projects/'.$project->id.'?customer='.$customer->id)
            ->assertOk();
    }
}
