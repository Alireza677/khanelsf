<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProjectCategoryResource;
use App\Filament\Resources\ProjectResource;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use App\Services\ModuleCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_projects_hide_frontend_menu_links_and_keep_data(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        $project = Project::factory()->published()->create();
        $category = ProjectCategory::factory()->create();
        $this->menuWithModuleLinks('main');
        $this->menuWithModuleLinks('footer');

        $this->setting('projects_enabled', '0', 'projects', 'boolean');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('/projects', false)
            ->assertSee('/shop', false);

        $this->assertDatabaseHas('projects', ['id' => $project->id]);
        $this->assertDatabaseHas('project_categories', ['id' => $category->id]);
    }

    public function test_disabled_shop_hides_frontend_menu_links_and_keeps_data(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        $product = Product::factory()->published()->create();
        $category = ProductCategory::factory()->create();
        $this->menuWithModuleLinks('main');
        $this->menuWithModuleLinks('footer');

        $this->setting('shop_enabled', '0', 'shop', 'boolean');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('/shop', false)
            ->assertDontSee('/cart', false)
            ->assertDontSee('/checkout', false)
            ->assertSee('/projects', false);

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('product_categories', ['id' => $category->id]);
    }

    public function test_disabled_module_resources_are_hidden_from_filament_navigation(): void
    {
        $this->setting('projects_enabled', '0', 'projects', 'boolean');
        $this->setting('shop_enabled', '0', 'shop', 'boolean');

        $this->assertFalse(ProjectResource::shouldRegisterNavigation());
        $this->assertFalse(ProjectCategoryResource::shouldRegisterNavigation());
        $this->assertFalse(ProductResource::shouldRegisterNavigation());
        $this->assertFalse(ProductCategoryResource::shouldRegisterNavigation());
        $this->assertFalse(OrderResource::shouldRegisterNavigation());
    }

    public function test_disabled_modules_are_excluded_from_sitemap_and_routes_are_disabled(): void
    {
        Project::factory()->published()->create(['slug' => 'hidden-project']);
        Product::factory()->published()->create(['slug' => 'hidden-product']);

        $this->setting('projects_enabled', '0', 'projects', 'boolean');
        $this->setting('shop_enabled', '0', 'shop', 'boolean');

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee('/projects', false)
            ->assertDontSee('/shop', false);

        $this->get(route('projects.index'))->assertStatus(301)->assertRedirect(route('galleries.index'));
        $this->get(route('galleries.index'))->assertNotFound();
        $this->get(route('projects.show', 'hidden-project'))->assertNotFound();
        $this->get(route('shop.index'))->assertNotFound();
        $this->get(route('shop.show', 'hidden-product'))->assertNotFound();
        $this->get(route('cart.index'))->assertNotFound();
        $this->get(route('checkout.create'))->assertNotFound();
    }

    public function test_cleanup_projects_deletes_projects_and_categories(): void
    {
        Project::factory()->count(2)->published()->create();
        ProjectCategory::factory()->count(2)->create();

        app(ModuleCleanupService::class)->deleteProjects();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('project_categories', 0);
    }

    public function test_cleanup_shop_deletes_products_categories_orders_and_items(): void
    {
        Product::factory()->count(2)->published()->create();
        ProductCategory::factory()->count(2)->create();

        $order = Order::query()->create([
            'order_number' => 'ORD-CLEANUP',
            'customer_name' => 'Cleanup Customer',
            'customer_phone' => '+1 555 000 9000',
            'status' => Order::STATUS_PENDING,
            'subtotal' => 20,
            'total' => 20,
            'payment_method' => 'manual',
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
        ]);

        $order->items()->create([
            'product_title' => 'Cleanup Item',
            'unit_price' => 20,
            'quantity' => 1,
            'total' => 20,
        ]);

        app(ModuleCleanupService::class)->deleteShop();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_categories', 0);
    }

    private function menuWithModuleLinks(string $location): void
    {
        $menu = Menu::query()->create([
            'title' => ucfirst($location).' Menu',
            'slug' => $location.'-menu',
            'location' => $location,
            'status' => 'active',
        ]);

        foreach ([
            ['Projects', '/projects'],
            ['Project Detail', '/projects/sample'],
            ['Shop', '/shop'],
            ['Product Detail', '/shop/sample'],
            ['Cart', '/cart'],
            ['Checkout', '/checkout'],
            ['Blog', '/blog'],
        ] as $index => [$title, $url]) {
            $menu->items()->create([
                'title' => $title,
                'url' => $url,
                'target' => '_self',
                'sort_order' => $index + 1,
                'status' => 'active',
            ]);
        }
    }

    private function setting(string $key, string $value, string $group, string $type): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'type' => $type,
            ],
        );
    }
}
