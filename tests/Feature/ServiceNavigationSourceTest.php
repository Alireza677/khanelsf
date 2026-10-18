<?php

namespace Tests\Feature;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\CMS\Navigation\Sources\ServiceNavigationSource;
use App\Filament\Resources\MenuResource\Pages\EditMenu;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Services\MenuService;
use App\Services\NavigationSourceVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceNavigationSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_picker_loads_bounded_branches_and_searches_deep_descendants_with_their_ancestors(): void
    {
        $root = $this->service('root');
        $child = $this->service('child', $root);
        $deep = $this->service('deep', $child);
        foreach (range(1, 30) as $index) {
            $this->service('root-'.$index)->update(['sort_order' => $index]);
        }
        $source = app(ServiceNavigationSource::class);
        $collapsed = $source->browse([], []);
        $this->assertCount(30, $collapsed['items']);
        $this->assertTrue($collapsed['has_more']);
        $this->assertNull($collapsed['items'][0]['children']);
        $this->assertTrue($collapsed['items'][0]['has_children']);
        $this->assertCount(1, $source->browse([], [0 => 2])['items']);

        $tree = $source->browse([$root->id, $child->id], []);
        $this->assertSame($deep->id, $tree['items'][0]['children']['items'][0]['children']['items'][0]['id']);
        $search = $source->search('deep', 1);
        $this->assertSame($root->id, $search['items'][0]['id']);
        $this->assertSame($child->id, $search['items'][0]['children']['items'][0]['id']);
        $this->assertSame($deep->id, $search['items'][0]['children']['items'][0]['children']['items'][0]['id']);
        $this->assertSame([], $source->search('%', 1)['items']);
    }

    public function test_picker_uses_publication_rules_and_keeps_public_children_of_hidden_parents_selectable(): void
    {
        $legacy = $this->service('legacy');
        $legacy->update(['status' => Service::STATUS_ACTIVE]);
        $draft = $this->service('draft');
        $draft->update(['status' => Service::STATUS_DRAFT]);
        $child = $this->service('public-child', $draft);
        $this->service('future')->update(['published_at' => now()->addDay()]);
        $this->service('archived')->update(['status' => Service::STATUS_ARCHIVED]);
        $this->service('empty-slug')->update(['slug' => '']);

        $ids = array_column(app(ServiceNavigationSource::class)->browse([], [])['items'], 'id');
        $this->assertEqualsCanonicalizing([$legacy->id, $child->id], $ids);
    }

    public function test_editor_adds_independent_entity_references_and_preserves_editing_and_menu_hierarchy(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = $this->menu();
        $root = $this->service('root');
        $child = $this->service('child', $root);
        $deep = $this->service('deep', $child);
        $editor = Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->assertSee('خدمات منتشرشده و زیرخدمات')
            ->assertDontSee('data-service-id=', false)
            ->call('loadServices')
            ->assertSee('data-service-id="'.$root->id.'"', false)
            ->call('toggleServiceBranch', $root->id)
            ->call('toggleServiceBranch', $child->id)
            ->assertSee('data-service-depth="2"', false)
            ->set('selectedServiceIds', [$deep->id, $root->id])
            ->call('addSelectedServices')
            ->assertHasNoErrors()
            ->assertSet('selectedServiceIds', []);

        $items = $menu->items()->orderBy('sort_order')->get();
        $this->assertSame([$deep->id, $root->id], $items->pluck('reference_id')->all());
        foreach ($items as $item) {
            $this->assertSame(MenuItem::TYPE_SERVICE, $item->type);
            $this->assertSame($root->getMorphClass(), $item->reference_type);
            $this->assertNull($item->parent_id);
            $this->assertNull($item->url);
            $this->assertNull($item->source_key);
        }
        $editor->call('updateMenuItem', $items[0]->id, [
            'title' => 'عنوان دلخواه', 'target' => '_blank', 'status' => 'active',
        ])->assertHasNoErrors()
            ->call('saveMenuTree', [[
                'id' => $items[1]->id,
                'children' => [['id' => $items[0]->id, 'children' => []]],
            ]])->assertHasNoErrors();
        $this->assertSame($items[1]->id, $items[0]->fresh()->parent_id);
        $deep->update(['slug' => 'changed-slug', 'name' => 'changed-name']);
        $this->assertSame('/services/changed-slug', $items[0]->fresh()->resolvedUrl());
        $this->assertSame('عنوان دلخواه', $items[0]->fresh()->title);
        $editor->call('deleteMenuItem', $items[0]->id)->assertHasNoErrors();
        $this->assertDatabaseMissing('menu_items', ['id' => $items[0]->id]);
        $this->assertDatabaseHas('services', ['id' => $deep->id]);
    }

    public function test_stale_or_tampered_selection_is_rejected_without_partial_insertion(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = $this->menu();
        $public = $this->service('public');
        $draft = $this->service('draft');
        $draft->update(['status' => Service::STATUS_DRAFT]);
        $editor = Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()]);
        foreach ([[$public->id, $draft->id], [$public->id, 999999], [$public->id, $public->id]] as $ids) {
            $editor->set('selectedServiceIds', $ids)->call('addSelectedServices')->assertHasErrors();
            $this->assertSame(0, $menu->items()->count());
        }
        $this->disablePublicServices();
        $editor->set('selectedServiceIds', [$public->id])->call('addSelectedServices')->assertHasErrors('selectedServiceIds');
        $this->assertSame(0, $menu->items()->count());
    }

    public function test_unavailable_services_are_retained_in_admin_and_omitted_from_public_navigation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = $this->menu();
        $service = $this->service('public');
        $item = $menu->items()->create([
            'type' => MenuItem::TYPE_SERVICE, 'reference_id' => $service->id,
            'reference_type' => $service->getMorphClass(), 'title' => $service->name,
            'target' => '_self', 'sort_order' => 0, 'status' => 'active',
        ]);
        $this->assertCount(1, app(MenuService::class)->byId($menu->id)->rootItems);
        foreach ([Service::STATUS_DRAFT, Service::STATUS_ARCHIVED, Service::STATUS_INACTIVE] as $status) {
            $service->update(['status' => $status]);
            $this->assertCount(0, app(MenuService::class)->byId($menu->id)->rootItems);
            $this->assertDatabaseHas('menu_items', ['id' => $item->id]);
        }
        $service->update(['status' => Service::STATUS_PUBLISHED, 'published_at' => now()->addDay()]);
        $this->assertFalse(app(NavigationSourceVisibility::class)->menuItemIsVisible($item->fresh()));
        $service->update(['published_at' => null]);
        $routes = app('router')->getRoutes();
        try {
            app('router')->setRoutes(new RouteCollection);
            $this->assertSame([], app(ServiceNavigationSource::class)->selected([$service->id])->all());
            $this->assertNull($item->fresh()->resolvedUrl());
        } finally {
            app('router')->setRoutes($routes);
        }
        $this->assertSame('/services', app(NavigationSourceRegistry::class)->resolve('services.archive'));
        $this->disablePublicServices();
        $this->assertCount(0, app(MenuService::class)->byId($menu->id)->rootItems);
        Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])->assertSee('مقصد در دسترس نیست');
        $service->delete();
        $this->assertCount(0, app(MenuService::class)->byId($menu->id)->rootItems);
        $this->assertDatabaseHas('menu_items', ['id' => $item->id]);
    }

    private function service(string $slug, ?Service $parent = null): Service
    {
        return Service::query()->create([
            'name' => $slug, 'slug' => $slug, 'parent_id' => $parent?->id,
            'status' => Service::STATUS_PUBLISHED, 'sort_order' => 0,
        ]);
    }

    private function menu(): Menu
    {
        return Menu::query()->create(['title' => 'منوی تست', 'slug' => 'test-menu', 'status' => 'active']);
    }

    private function disablePublicServices(): void
    {
        Setting::query()->updateOrCreate(['key' => 'public_services_enabled'], [
            'value' => '0', 'group' => 'services', 'type' => 'boolean',
        ]);
    }
}
