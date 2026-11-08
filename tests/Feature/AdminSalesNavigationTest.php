<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ServiceOfferingResource;
use App\Filament\Resources\ServiceResource;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdminSalesNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_sales_navigation_has_the_five_entries_in_order_without_a_separate_shop_group(): void
    {
        $groups = collect(Filament::getNavigation());
        $salesGroups = $groups->filter(fn ($group) => $group->getLabel() === 'خدمات و فروش');

        $this->assertCount(1, $salesGroups);
        $this->assertSame(
            ['بسته‌های خدمات', 'خدمات', 'محصولات', 'دسته‌های محصول', 'سفارش‌ها'],
            collect($salesGroups->first()->getItems())->map->getLabel()->values()->all(),
        );
        $this->assertSame([
            'محتوای وب‌سایت',
            'خدمات و فروش',
            'نمونه‌کار و گالری',
            'فروش و ارتباط با مشتری',
            'پرتال مشتریان',
            'ساختار و طراحی وب‌سایت',
            'نگهداری سیستم',
        ], $groups->map->getLabel()->filter()->values()->all());

        $urls = $groups->flatMap(fn ($group) => $group->getItems())->map->getUrl();
        foreach ($this->resourcePaths() as $resource => $path) {
            $this->assertSame(1, $urls->filter(fn ($url) => $url === $resource::getUrl())->count());
        }
    }

    public function test_navigation_links_still_open_the_existing_resource_pages_in_rtl(): void
    {
        foreach ($this->resourcePaths() as $resource => $path) {
            $url = $resource::getUrl();
            $this->assertSame($path, parse_url($url, PHP_URL_PATH));
            $this->get($url)->assertOk()->assertSee('dir="rtl"', false);
        }
    }

    public function test_disabling_shop_hides_only_the_shop_entries_from_the_sales_group(): void
    {
        Setting::query()->updateOrCreate(['key' => 'shop_enabled'], [
            'value' => '0', 'group' => 'shop', 'type' => 'boolean',
        ]);

        $groups = collect(Filament::getNavigation());
        $salesGroup = $groups->first(fn ($group) => $group->getLabel() === 'خدمات و فروش');

        $this->assertSame(['بسته‌های خدمات', 'خدمات'], collect($salesGroup->getItems())->map->getLabel()->values()->all());
        $this->assertNotContains('فروشگاه', $groups->map->getLabel()->all());
        foreach ([ProductResource::class, ProductCategoryResource::class, OrderResource::class] as $resource) {
            $this->assertFalse($resource::shouldRegisterNavigation());
        }
    }

    public function test_denied_order_permission_hides_orders_and_keeps_the_other_sales_entries(): void
    {
        Gate::before(fn ($user, $ability, $arguments) => $ability === 'viewAny' && ($arguments[0] ?? null) === Order::class ? false : null);

        $salesGroup = collect(Filament::getNavigation())->first(fn ($group) => $group->getLabel() === 'خدمات و فروش');

        $this->assertSame(['بسته‌های خدمات', 'خدمات', 'محصولات', 'دسته‌های محصول'], collect($salesGroup->getItems())->map->getLabel()->values()->all());
        $this->get(OrderResource::getUrl())->assertForbidden();
    }

    private function resourcePaths(): array
    {
        return [
            ServiceOfferingResource::class => '/admin/service-offerings',
            ServiceResource::class => '/admin/services',
            ProductResource::class => '/admin/products',
            ProductCategoryResource::class => '/admin/product-categories',
            OrderResource::class => '/admin/orders',
        ];
    }
}
