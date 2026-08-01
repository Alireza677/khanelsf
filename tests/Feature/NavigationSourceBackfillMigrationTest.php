<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NavigationSourceBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_shop_urls_are_backfilled_without_creating_items(): void
    {
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'main',
            'status' => 'active',
        ]);
        $shop = $menu->items()->create([
            'title' => 'فروشگاه',
            'url' => '/shop',
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'status' => 'active',
        ]);
        $shopWithSlash = $menu->items()->create([
            'title' => 'فروشگاه دوم',
            'url' => '/shop/',
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'status' => 'active',
        ]);
        $other = $menu->items()->create([
            'title' => 'درباره ما',
            'url' => '/about',
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'status' => 'active',
        ]);
        $countBefore = MenuItem::query()->count();

        Schema::table('menu_items', function (Blueprint $table): void {
            $table->dropIndex(['source_key']);
            $table->dropColumn('source_key');
        });

        $migration = require database_path('migrations/2026_07_28_000000_add_source_key_to_menu_items_table.php');
        $migration->up();

        $this->assertDatabaseHas('menu_items', [
            'id' => $shop->getKey(),
            'source_key' => 'shop.index',
            'type' => MenuItem::TYPE_SOURCE,
            'url' => null,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $shopWithSlash->getKey(),
            'source_key' => 'shop.index',
            'type' => MenuItem::TYPE_SOURCE,
            'url' => null,
        ]);
        $this->assertDatabaseHas('menu_items', [
            'id' => $other->getKey(),
            'source_key' => null,
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => '/about',
        ]);
        $this->assertSame($countBefore, MenuItem::query()->count());
    }
}
