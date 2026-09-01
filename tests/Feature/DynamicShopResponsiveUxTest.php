<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicShopResponsiveUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_renders_one_real_filter_form_and_the_existing_wishlist_action(): void
    {
        $product = Product::factory()->published()->create([
            'title' => 'محصولی با عنوان طولانی برای بررسی چیدمان واکنش‌گرا',
        ]);

        Template::query()->create([
            'title' => 'Responsive Shop',
            'slug' => 'responsive-shop',
            'type' => 'shop_index',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [[
                'type' => 'template_shop_complete',
                'data' => ['title' => 'Responsive Shop'],
            ]],
        ]);

        $response = $this->get(route('shop.index'))->assertOk();
        $html = $response->getContent();

        $response
            ->assertSee('data-shop-filter-drawer', false)
            ->assertSee('data-shop-filter-panel', false)
            ->assertSee('aria-controls="shop-product-filters"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('فیلتر محصولات')
            ->assertSee($product->title)
            ->assertSee('action="'.route('shop.favorites.toggle', $product).'"', false)
            ->assertSee('aria-pressed="false"', false);

        $this->assertSame(1, substr_count($html, 'data-shop-filter-panel'));
        $this->assertSame(1, substr_count($html, 'id="shop-product-filters"'));
        $this->assertSame(1, substr_count($html, 'class="shop-template__filters"'));
    }

    public function test_product_cards_and_mobile_grid_follow_the_responsive_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.shop-template__product-card\s*\{[^}]*background:\s*#fff;[^}]*border:\s*1px solid #e5e7eb;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(hover: hover\) and \(pointer: fine\).*?\.shop-template__heart\s*\{[^}]*opacity:\s*0;[^}]*pointer-events:\s*none;.*?\.shop-template__product-card:hover \.shop-template__heart/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(hover: none\), \(pointer: coarse\).*?\.shop-template__heart\s*\{[^}]*height:\s*44px;[^}]*width:\s*44px;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\).*?\.shop-template__product-grid\s*\{[^}]*grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);/s',
            $css,
        );
        $this->assertStringContainsString('-webkit-line-clamp: 2;', $css);
    }

    public function test_filter_drawer_reuses_the_project_overlay_accessibility_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        foreach ([
            "window.matchMedia('(max-width: 900px)')",
            "event.key === 'Escape'",
            "event.key !== 'Tab'",
            "document.body.classList.add('shop-filter-drawer-open')",
            "panel.setAttribute('role', 'dialog')",
            "panel.setAttribute('aria-modal', 'true')",
            'element.inert = true',
        ] as $contract) {
            $this->assertStringContainsString($contract, $javascript);
        }

        $this->assertMatchesRegularExpression(
            '/\.shop-template\.is-filter-drawer-ready \.shop-template__filters\s*\{[^}]*height:\s*100dvh;[^}]*position:\s*fixed;[^}]*width:\s*min\(88vw, 22rem\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template\.is-filter-drawer-ready \.shop-template__filters form\s*\{[^}]*overflow-y:\s*auto;/s',
            $css,
        );
        $this->assertStringContainsString('body.shop-filter-drawer-open', $css);
    }
}
