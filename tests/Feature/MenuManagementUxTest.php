<?php

namespace Tests\Feature;

use App\Filament\Resources\MenuItemResource;
use App\Filament\Resources\MenuResource;
use App\Filament\Resources\MenuResource\Pages\EditMenu;
use App\Filament\Resources\MenuResource\Pages\ListMenus;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Services\MenuService;
use App\Services\MenuTreeService;
use App\Services\NavigationSourceVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MenuManagementUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_index_is_the_single_entry_point_and_shows_an_empty_state(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListMenus::class)
            ->assertOk()
            ->assertSee('هنوز منویی ساخته نشده است')
            ->assertSee('ساخت اولین منو')
            ->assertActionExists('create');

        $this->assertFalse(Route::has('filament.admin.resources.menus.create'));
        $this->assertFalse(Route::has('filament.admin.resources.menu-items.index'));
        $this->assertFalse(Route::has('filament.admin.resources.menu-items.create'));
        $this->assertFalse(MenuItemResource::shouldRegisterNavigation());
    }

    public function test_menu_can_be_created_by_name_from_the_management_page(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Menu::query()->create([
            'title' => 'منوی موجود',
            'slug' => 'main-menu',
            'status' => 'active',
        ]);

        Livewire::test(ListMenus::class)
            ->callAction('create', data: [
                'title' => 'Main Menu',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('menus', [
            'title' => 'Main Menu',
            'slug' => 'main-menu-2',
            'status' => 'active',
        ]);
    }

    public function test_menu_items_are_edited_and_deleted_inside_the_builder(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'main',
            'status' => 'active',
        ]);
        $parent = $this->menuItem($menu, 'والد', 0);
        $child = $this->menuItem($menu, 'فرزند', 0, $parent);

        $this->assertSame([], MenuResource::getRelations());

        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->assertSee('ذخیره تغییرات')
            ->assertSee('حذف آیتم')
            ->call('updateMenuItem', $child->getKey(), [
                'title' => '',
                'url' => '/updated',
                'target' => '_blank',
                'status' => 'inactive',
            ])
            ->assertHasErrors(["menuItems.{$child->getKey()}.title"])
            ->call('updateMenuItem', $child->getKey(), [
                'title' => 'فرزند ویرایش‌شده',
                'url' => '/updated',
                'target' => '_blank',
                'status' => 'inactive',
            ])
            ->assertHasNoErrors()
            ->call('deleteMenuItem', $parent->getKey())
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('menu_items', [
            'id' => $parent->getKey(),
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $child->getKey(),
            'menu_id' => $menu->getKey(),
            'parent_id' => null,
            'title' => 'فرزند ویرایش‌شده',
            'url' => '/updated',
            'target' => '_blank',
            'status' => 'inactive',
        ]);
    }

    public function test_pages_and_a_custom_url_can_be_added_from_the_menu_builder_column(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'main',
            'status' => 'active',
        ]);
        $home = Page::factory()->published()->create([
            'title' => 'خانه',
            'slug' => 'home',
        ]);
        $about = Page::factory()->published()->create([
            'title' => 'درباره ما',
            'slug' => 'about',
        ]);

        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->assertSee('افزودن آیتم')
            ->assertSee('برگه‌ها')
            ->assertSee('پیوند دلخواه')
            ->assertSee('ساختار منو')
            ->set('selectedPageIds', [$home->getKey(), $about->getKey()])
            ->call('addSelectedPages')
            ->assertHasNoErrors()
            ->set('customItemTitle', 'وب‌سایت همکار')
            ->set('customItemUrl', 'https://example.com')
            ->set('customItemTarget', '_blank')
            ->call('addCustomItem')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->getKey(),
            'title' => 'خانه',
            'url' => null,
            'target' => '_self',
            'type' => MenuItem::TYPE_PAGE,
            'source_key' => null,
            'reference_id' => $home->getKey(),
            'reference_type' => $home->getMorphClass(),
        ]);
        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->getKey(),
            'title' => 'درباره ما',
            'url' => null,
            'target' => '_self',
        ]);
        $this->assertDatabaseHas('menu_items', [
            'menu_id' => $menu->getKey(),
            'title' => 'وب‌سایت همکار',
            'url' => 'https://example.com',
            'target' => '_blank',
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'reference_id' => null,
            'reference_type' => null,
        ]);

        $pageItem = MenuItem::query()
            ->where('menu_id', $menu->getKey())
            ->where('title', 'خانه')
            ->firstOrFail();

        $this->assertTrue($pageItem->reference->is($home));
    }

    public function test_available_navigation_source_can_be_added_and_resolves_without_owning_a_url(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'main',
            'status' => 'active',
        ]);
        Setting::query()->updateOrCreate(
            ['key' => 'shop_enabled'],
            ['value' => '1', 'group' => 'shop', 'type' => 'boolean'],
        );
        $shopPage = Page::factory()->published()->create([
            'title' => 'صفحه تکراری فروشگاه',
            'slug' => 'shop',
        ]);

        $component = Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->assertSee('مقصدهای سیستمی')
            ->assertSee('data-navigation-source="shop.index"', false)
            ->set('selectedSourceKeys', ['shop.index'])
            ->call('addSelectedSources')
            ->assertHasNoErrors();

        $component
            ->assertSee('shop.index')
            ->assertSee('URL از مقصد انتخاب‌شده resolve می‌شود.')
            ->set('selectedSourceKeys', ['unknown.index'])
            ->call('addSelectedSources')
            ->assertHasErrors(['selectedSourceKeys'])
            ->set('selectedPageIds', [$shopPage->getKey()])
            ->call('addSelectedPages')
            ->assertHasErrors(['selectedPageIds'])
            ->set('customItemTitle', 'فروشگاه سفارشی')
            ->set('customItemUrl', '/shop/')
            ->call('addCustomItem')
            ->assertHasErrors(['customItemUrl']);

        $item = $menu->items()->where('source_key', 'shop.index')->firstOrFail();

        $this->assertSame(1, $menu->items()->count());
        $this->assertSame(MenuItem::TYPE_SOURCE, $item->type);
        $this->assertNull($item->url);
        $this->assertNull($item->reference_id);
        $this->assertNull($item->reference_type);
        $this->assertSame('/shop', $item->resolvedUrl());
    }

    public function test_disabled_navigation_sources_are_hidden_and_rejected_by_the_builder_backend(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'main',
            'status' => 'active',
        ]);
        $shopPage = Page::factory()->published()->create([
            'title' => 'فروشگاه',
            'slug' => 'shop',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'shop_enabled'],
            ['value' => '0', 'group' => 'shop', 'type' => 'boolean'],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'projects_enabled'],
            ['value' => '0', 'group' => 'projects', 'type' => 'boolean'],
        );

        $visibleSourceKeys = collect(app(NavigationSourceVisibility::class)->visibleSources())
            ->pluck('source_key');

        $this->assertNotContains('shop.index', $visibleSourceKeys);

        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->assertDontSee('data-navigation-source="shop.index"', false)
            ->set('selectedSourceKeys', ['shop.index'])
            ->call('addSelectedSources')
            ->assertHasErrors(['selectedSourceKeys'])
            ->set('selectedPageIds', [$shopPage->getKey()])
            ->call('addSelectedPages')
            ->assertHasErrors(['selectedPageIds'])
            ->set('customItemTitle', 'پروژه‌ها')
            ->set('customItemUrl', '/projects')
            ->call('addCustomItem')
            ->assertHasErrors(['customItemUrl']);

        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_menu_renderer_excludes_inactive_and_disabled_source_items(): void
    {
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'main',
            'location' => 'main',
            'status' => 'active',
        ]);

        $menu->items()->createMany([
            [
                'type' => MenuItem::TYPE_POST,
                'title' => 'نوشته فعال',
                'url' => '/blog/active',
                'target' => '_self',
                'sort_order' => 0,
                'status' => 'active',
            ],
            [
                'type' => MenuItem::TYPE_POST,
                'title' => 'نوشته غیرفعال',
                'url' => '/blog/inactive',
                'target' => '_self',
                'sort_order' => 1,
                'status' => 'inactive',
            ],
            [
                'type' => MenuItem::TYPE_SOURCE,
                'source_key' => 'shop.index',
                'title' => 'فروشگاه مخفی',
                'url' => null,
                'target' => '_self',
                'sort_order' => 2,
                'status' => 'active',
            ],
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'shop_enabled'],
            ['value' => '0', 'group' => 'shop', 'type' => 'boolean'],
        );

        $renderedItems = app(MenuService::class)->main()?->rootItems;

        $this->assertSame(['نوشته فعال'], $renderedItems?->pluck('title')->all());
    }

    public function test_selected_settings_menus_override_legacy_header_and_footer_locations(): void
    {
        $legacyHeader = Menu::query()->create([
            'title' => 'هدر قدیمی',
            'slug' => 'legacy-header',
            'location' => 'main',
            'status' => 'active',
        ]);
        $legacyFooter = Menu::query()->create([
            'title' => 'فوتر قدیمی',
            'slug' => 'legacy-footer',
            'location' => 'footer',
            'status' => 'active',
        ]);
        $selectedHeader = Menu::query()->create([
            'title' => 'هدر انتخاب‌شده',
            'slug' => 'selected-header',
            'status' => 'active',
        ]);
        $selectedFooter = Menu::query()->create([
            'title' => 'فوتر انتخاب‌شده',
            'slug' => 'selected-footer',
            'status' => 'active',
        ]);

        foreach ([
            [$legacyHeader, 'لینک هدر قدیمی'],
            [$legacyFooter, 'لینک فوتر قدیمی'],
            [$selectedHeader, 'لینک هدر جدید'],
            [$selectedFooter, 'لینک فوتر جدید'],
        ] as [$menu, $title]) {
            $menu->items()->create([
                'title' => $title,
                'url' => '/'.str($title)->slug(),
                'target' => '_self',
                'sort_order' => 0,
                'status' => 'active',
            ]);
        }

        Setting::query()->create([
            'key' => 'header_menu_id',
            'value' => (string) $selectedHeader->getKey(),
            'group' => 'header',
            'type' => 'select',
        ]);
        Setting::query()->create([
            'key' => 'footer_menu_id',
            'value' => (string) $selectedFooter->getKey(),
            'group' => 'footer',
            'type' => 'select',
        ]);

        $menus = app(MenuService::class);

        $this->assertTrue($menus->header()?->is($selectedHeader));
        $this->assertTrue($menus->main()?->is($selectedHeader));
        $this->assertTrue($menus->footer()?->is($selectedFooter));
        $this->assertSame(['لینک هدر جدید'], $menus->header()?->rootItems->pluck('title')->all());
        $this->assertSame(['لینک فوتر جدید'], $menus->footer()?->rootItems->pluck('title')->all());
    }

    public function test_menu_item_link_type_infrastructure_is_backward_compatible(): void
    {
        $this->assertTrue(Schema::hasColumns('menu_items', [
            'type',
            'source_key',
            'reference_id',
            'reference_type',
        ]));
        $this->assertSame([
            'page',
            'custom_url',
            'source',
            'post',
            'product',
            'project',
            'service',
        ], MenuItem::TYPES);

        $menu = Menu::query()->create([
            'title' => 'منوی قدیمی',
            'slug' => 'legacy-menu',
            'status' => 'active',
        ]);
        $legacyItem = $menu->items()->create([
            'title' => 'لینک قدیمی',
            'url' => '/legacy',
            'target' => '_self',
            'sort_order' => 0,
            'status' => 'active',
        ])->refresh();

        $this->assertSame(MenuItem::TYPE_CUSTOM_URL, $legacyItem->type);
        $this->assertNull($legacyItem->reference_id);
        $this->assertNull($legacyItem->reference_type);
        $this->assertNull($legacyItem->source_key);
        $this->assertNull($legacyItem->reference);
        $this->assertSame('/legacy', $legacyItem->url);
    }

    public function test_deleting_a_source_item_is_not_reversed_by_module_toggles(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'main',
            'status' => 'active',
        ]);
        Setting::query()->updateOrCreate(
            ['key' => 'shop_enabled'],
            ['value' => '1', 'group' => 'shop', 'type' => 'boolean'],
        );

        $component = Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->set('selectedSourceKeys', ['shop.index'])
            ->call('addSelectedSources')
            ->assertHasNoErrors();
        $item = $menu->items()->where('source_key', 'shop.index')->firstOrFail();

        $component->call('deleteMenuItem', $item->getKey())->assertHasNoErrors();
        Setting::query()->where('key', 'shop_enabled')->update(['value' => '0']);
        Setting::query()->where('key', 'shop_enabled')->update(['value' => '1']);

        $this->assertDatabaseMissing('menu_items', ['id' => $item->getKey()]);
        $this->assertDatabaseCount('menu_items', 0);
    }

    public function test_menu_tree_reorders_and_reparents_existing_items_without_changing_ids(): void
    {
        $menu = Menu::query()->create([
            'title' => 'منوی درختی',
            'slug' => 'tree-menu',
            'status' => 'active',
        ]);
        $first = $this->menuItem($menu, 'اول', 0);
        $second = $this->menuItem($menu, 'دوم', 1);
        $child = $this->menuItem($menu, 'فرزند', 0, $first);
        $originalIds = MenuItem::query()->where('menu_id', $menu->getKey())->pluck('id')->sort()->values()->all();

        app(MenuTreeService::class)->save($menu, [
            [
                'id' => $second->getKey(),
                'children' => [
                    [
                        'id' => $first->getKey(),
                        'children' => [
                            ['id' => $child->getKey(), 'children' => []],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertDatabaseHas('menu_items', [
            'id' => $second->getKey(),
            'parent_id' => null,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $first->getKey(),
            'parent_id' => $second->getKey(),
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $child->getKey(),
            'parent_id' => $first->getKey(),
            'sort_order' => 0,
        ]);
        $this->assertSame(
            $originalIds,
            MenuItem::query()->where('menu_id', $menu->getKey())->pluck('id')->sort()->values()->all(),
        );
    }

    public function test_menu_tree_rejects_missing_duplicate_and_foreign_menu_items(): void
    {
        $menu = Menu::query()->create([
            'title' => 'منوی امن',
            'slug' => 'safe-menu',
            'status' => 'active',
        ]);
        $item = $this->menuItem($menu, 'آیتم اصلی', 0);
        $otherMenu = Menu::query()->create([
            'title' => 'منوی دیگر',
            'slug' => 'other-menu',
            'status' => 'active',
        ]);
        $foreignItem = $this->menuItem($otherMenu, 'آیتم خارجی', 0);

        try {
            app(MenuTreeService::class)->save($menu, [
                ['id' => $item->getKey(), 'children' => []],
                ['id' => $foreignItem->getKey(), 'children' => []],
            ]);
            $this->fail('A foreign menu item should not be accepted.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'ساختار ارسالی با آیتم‌های این منو مطابقت ندارد.',
                $exception->validator->errors()->first('menuTree'),
            );
        }

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->getKey(),
            'menu_id' => $menu->getKey(),
            'parent_id' => null,
            'sort_order' => 0,
        ]);
    }

    private function menuItem(
        Menu $menu,
        string $title,
        int $sortOrder,
        ?MenuItem $parent = null,
    ): MenuItem {
        return $menu->items()->create([
            'parent_id' => $parent?->getKey(),
            'title' => $title,
            'url' => '/'.str($title)->slug(),
            'target' => '_self',
            'sort_order' => $sortOrder,
            'status' => 'active',
        ]);
    }
}
