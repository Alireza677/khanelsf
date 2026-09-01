<?php

namespace Tests\Feature;

use Tests\TestCase;

class DynamicShopLayoutTest extends TestCase
{
    public function test_categories_and_catalog_share_one_outer_container_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $shopStart = strpos($css, '.shop-template {');
        $shopEnd = strpos($css, '.project-detail h1', $shopStart);
        $shopCss = substr($css, $shopStart, $shopEnd - $shopStart);

        $this->assertMatchesRegularExpression(
            '/\.shop-template__categories,\s*\.shop-template__catalog\s*\{\s*@apply\s+box-border\s+mx-auto\s+max-w-\[1400px\]\s+px-5;\s*\}/s',
            $shopCss,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__categories\s*\{\s*@apply\s+relative\s+z-10\s+-mt-20\s+rounded-\[8px\]\s+py-3;/s',
            $shopCss,
        );
        $this->assertStringNotContainsString('max-width: var(--theme-container-width, 1400px)', $shopCss);
    }

    public function test_desktop_gives_remaining_width_to_products_while_sidebar_stays_bounded(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.shop-template__catalog\s*\{[^}]*@apply\s+flex\s+items-start\s+gap-4/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__filters\s*\{[^}]*@apply[^;]*w-\[300px\][^;]*shrink-0/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__products\s*\{[^}]*@apply\s+flex\s+min-w-0\s+flex-1/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__product-grid\s*\{[^}]*@apply\s+grid\s+grid-cols-4/s',
            $css,
        );
    }

    public function test_tablet_and_mobile_layouts_collapse_without_page_level_horizontal_overflow(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 900px\).*?\.shop-template__catalog\s*\{[^}]*flex-direction:\s*column;[^}]*\}.*?\.shop-template\.is-filter-drawer-ready \.shop-template__filters\s*\{[^}]*position:\s*fixed;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 900px\).*?\.shop-template__product-grid\s*\{[^}]*grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\).*?\.shop-template__product-grid\s*\{[^}]*grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);/s',
            $css,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.shop-template__product-grid,\s*\.shop-template__price-fields\s*\{[^}]*grid-template-columns:\s*1fr;/s',
            $css,
        );
        $this->assertStringContainsString('@apply flex min-w-0 flex-1 flex-col;', $css);
    }

    public function test_catalog_is_the_single_wrapper_for_products_and_filters(): void
    {
        $source = file_get_contents(resource_path('views/partials/blocks/template_shop_complete.blade.php'));
        $start = strpos($source, '<section class="shop-template__catalog"');
        $end = strpos($source, '</section>', $start);
        $catalog = substr($source, $start, $end - $start);

        $this->assertStringContainsString('class="shop-template__filters"', $catalog);
        $this->assertStringContainsString('<div class="shop-template__products">', $catalog);
        $this->assertStringNotContainsString('class="container"', $catalog);
        $this->assertLessThan(
            strpos($catalog, '<div class="shop-template__products">'),
            strpos($catalog, '<aside class="shop-template__filters"'),
        );
    }
}
