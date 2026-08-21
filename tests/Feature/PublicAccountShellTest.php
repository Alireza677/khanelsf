<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\CustomerMembershipManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAccountShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_account_home(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }

    public function test_admin_cannot_use_the_client_guard_to_access_account_home(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin, 'client')
            ->get('/account')
            ->assertRedirect(route('login'));

        $this->assertGuest('client');
    }

    public function test_public_user_without_membership_sees_a_normal_account_home(): void
    {
        $user = User::factory()->client()->create([
            'name' => 'کاربر عمومی',
            'mobile' => '09120000001',
        ]);

        $this->actingAs($user, 'client')
            ->get('/account')
            ->assertOk()
            ->assertSee('public-account-status-badge', false)
            ->assertSee('فعال')
            ->assertDontSee('حساب کاربری شما فعال است.')
            ->assertSee('کاربر عمومی')
            ->assertSee('09120000001')
            ->assertSee('پروفایل من')
            ->assertDontSee('پروژه‌های من')
            ->assertDontSee('پرتال خدمات');
    }

    public function test_active_customer_member_sees_project_and_service_navigation(): void
    {
        $user = User::factory()->client()->create();
        $customer = Customer::factory()->create(['status' => Customer::STATUS_ACTIVE]);
        app(CustomerMembershipManager::class)->assign($customer, $user, 'member');

        $this->actingAs($user, 'client')
            ->get('/account')
            ->assertOk()
            ->assertSee('خدمات و پروژه‌های من')
            ->assertSee(route('account.services.index'), false)
            ->assertDontSee('پرتال خدمات');
    }

    public function test_admin_on_web_guard_is_a_guest_in_the_fallback_public_header(): void
    {
        $this->home();
        $admin = User::factory()->admin()->create(['name' => 'مدیر داخلی']);

        $this->actingAs($admin, 'web')
            ->get('/')
            ->assertOk()
            ->assertSee(route('login'), false)
            ->assertSee(route('register'), false)
            ->assertDontSee('مدیر داخلی');
    }

    public function test_fallback_header_switches_from_guest_actions_to_public_account_menu(): void
    {
        $this->home();

        $this->get('/')
            ->assertOk()
            ->assertSee('ورود')
            ->assertSee('ثبت‌نام');

        $user = User::factory()->client()->create(['name' => 'عضو عمومی']);

        $this->actingAs($user, 'client')
            ->get('/')
            ->assertOk()
            ->assertSee('عضو عمومی')
            ->assertSee('حساب کاربری')
            ->assertSee('پروفایل من')
            ->assertSee('سفارش‌های من')
            ->assertDontSee(route('login'), false)
            ->assertDontSee(route('register'), false);
    }

    public function test_authenticated_header_logout_is_a_csrf_protected_post_form(): void
    {
        $this->home();
        $user = User::factory()->client()->create();

        $this->actingAs($user, 'client')
            ->get('/')
            ->assertOk()
            ->assertSee('method="POST" action="'.route('client.logout').'"', false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('href="'.route('client.logout').'"', false);
    }

    public function test_template_header_uses_the_same_guest_and_authenticated_account_states(): void
    {
        $this->home();
        $this->selectHeaderTemplate();

        $this->get('/')
            ->assertOk()
            ->assertSee('industrial-header', false)
            ->assertSee('public-account-controls__guest-icon', false)
            ->assertSee('aria-label="ورود به حساب کاربری"', false)
            ->assertDontSee('public-account-controls__register', false)
            ->assertDontSee('public-account-menu__dropdown', false);

        $user = User::factory()->client()->create(['name' => 'کاربر قالب']);

        $this->actingAs($user, 'client')
            ->get('/')
            ->assertOk()
            ->assertSee('industrial-header', false)
            ->assertSee('کاربر قالب')
            ->assertSee(route('account.home'), false)
            ->assertSee(route('account.orders.index'), false)
            ->assertDontSee(route('register'), false);
    }

    private function home(): void
    {
        Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'خانه',
            'blocks' => [],
        ]);
    }

    private function selectHeaderTemplate(): void
    {
        $template = Template::query()->create([
            'title' => 'هدر حساب عمومی',
            'slug' => 'public-account-header',
            'type' => 'site_header',
            'status' => 'published',
            'is_default' => false,
            'conditions' => ['type' => 'all'],
            'blocks' => [[
                'type' => 'site_header',
                'data' => [
                    'block_id' => '01JACCOUNTHEADER00000000000',
                    'schema_version' => 1,
                    'template' => 'industrial-header-v1',
                    'content' => [
                        'top_actions' => [],
                        'primary_action' => [],
                    ],
                    'settings' => [
                        'menu_id' => null,
                        'search_enabled' => false,
                        'sticky_enabled' => true,
                        'top_bar_enabled' => false,
                    ],
                ],
            ]],
        ]);

        Setting::query()->create([
            'key' => 'header_template_id',
            'value' => (string) $template->getKey(),
            'group' => 'header',
            'type' => 'select',
        ]);
    }
}
