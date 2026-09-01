<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicShopMobileToolbarTest extends TestCase
{
    use RefreshDatabase;

    public function test_toolbar_reuses_existing_wishlist_and_filter_actions_with_accessible_icons(): void
    {
        $product = Product::factory()->published()->create(['title' => 'Toolbar Product']);

        Template::query()->create([
            'title' => 'Toolbar Shop',
            'slug' => 'toolbar-shop',
            'type' => 'shop_index',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [[
                'type' => 'template_shop_complete',
                'data' => ['title' => 'Toolbar Shop'],
            ]],
        ]);

        $response = $this->get(route('shop.index', ['q' => 'Toolbar']))->assertOk();

        $response
            ->assertSee('class="shop-template__filter-toggle has-active-filters"', false)
            ->assertSee('aria-label="فیلتر محصولات، فیلتر فعال است"', false)
            ->assertSee('data-shop-filter-toggle', false)
            ->assertSee('class="shop-template__sort-icon icon-sort"', false)
            ->assertSee('class="shop-template__sort-chevron icon-arrow-down-1"', false)
            ->assertSee('aria-label="مرتب‌سازی محصولات"', false)
            ->assertSee('class="icon-heart"', false)
            ->assertSee('aria-label="مشاهده علاقه‌مندی‌ها"', false)
            ->assertSee(route('shop.index', ['q' => 'Toolbar', 'favorites' => '1']))
            ->assertSee($product->title);
    }

    public function test_mobile_toolbar_is_one_compact_row_while_desktop_icons_stay_hidden(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.shop-template__favorites-filter \.icon-heart,\s*\.shop-template__sort-icon,\s*\.shop-template__sort-chevron\s*\{\s*display:\s*none;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\).*?\.shop-template__toolbar-actions\s*\{[^}]*display:\s*grid;[^}]*grid-template-columns:\s*auto minmax\(0, 1fr\) auto;[^}]*width:\s*100%;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__sort\s*\{[^}]*grid-template-columns:\s*auto minmax\(0, 1fr\) auto;[^}]*min-height:\s*44px;[^}]*width:\s*100%;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__favorites-filter,\s*\.shop-template__filter-toggle\s*\{[^}]*height:\s*44px;[^}]*min-height:\s*44px;[^}]*width:\s*44px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__favorites-filter > span,\s*\.shop-template__filter-toggle > span\s*\{\s*display:\s*none;/s',
            $css,
        );
    }
}
