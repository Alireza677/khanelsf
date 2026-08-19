<?php

namespace Tests\Feature;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\Filament\Resources\MenuResource\Pages\EditMenu;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\MenuService;
use App\Services\NavigationSourceVisibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class BlogNavigationSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_archive_is_registered_available_and_resolved_at_runtime(): void
    {
        $registry = app(NavigationSourceRegistry::class);
        $source = $registry->find('blog.archive');

        $this->assertNotNull($source);
        $this->assertSame('وبلاگ', $source->label);
        $this->assertNull($source->module);
        $this->assertTrue($source->isAvailable());
        $this->assertSame(route('blog.index', absolute: false), $source->resolve());
        $this->assertContains(
            'blog.archive',
            collect(app(NavigationSourceVisibility::class)->visibleSources())->pluck('source_key')->all(),
        );

        $routes = Route::getRoutes();

        try {
            app('router')->setRoutes(new RouteCollection);
            $this->assertFalse($source->isAvailable());
            $this->assertNull($source->resolve());
        } finally {
            app('router')->setRoutes($routes);
        }
    }

    public function test_blog_can_be_deleted_and_readded_from_system_destinations_as_a_source_reference(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'main',
            'location' => 'main',
            'status' => 'active',
        ]);

        $component = Livewire::test(EditMenu::class, ['record' => $menu->getRouteKey()])
            ->assertSee('مقصدهای سیستمی')
            ->assertSee('data-navigation-source="blog.archive"', false)
            ->assertSee('وبلاگ')
            ->assertSee('/blog')
            ->set('selectedSourceKeys', ['blog.archive'])
            ->call('addSelectedSources')
            ->assertHasNoErrors();

        $item = $menu->items()->where('source_key', 'blog.archive')->firstOrFail();

        $this->assertSame(MenuItem::TYPE_SOURCE, $item->type);
        $this->assertNull($item->url);
        $this->assertSame('/blog', $item->resolvedUrl());
        $this->assertSame(
            ['blog.archive'],
            app(MenuService::class)->main()?->rootItems->pluck('source_key')->all(),
        );

        $component->call('deleteMenuItem', $item->getKey())->assertHasNoErrors();

        $this->assertDatabaseMissing('menu_items', ['id' => $item->getKey()]);
        $this->assertSame([], app(MenuService::class)->main()?->rootItems->all());

        $component
            ->assertSee('data-navigation-source="blog.archive"', false)
            ->set('selectedSourceKeys', ['blog.archive'])
            ->call('addSelectedSources')
            ->assertHasNoErrors();

        $readdedItem = $menu->items()->where('source_key', 'blog.archive')->firstOrFail();

        $this->assertSame(MenuItem::TYPE_SOURCE, $readdedItem->type);
        $this->assertNull($readdedItem->url);
        $this->assertSame('/blog', $readdedItem->resolvedUrl());
        $this->assertSame(
            ['/blog'],
            app(MenuService::class)->main()?->rootItems->map->resolvedUrl()->all(),
        );
    }
}
