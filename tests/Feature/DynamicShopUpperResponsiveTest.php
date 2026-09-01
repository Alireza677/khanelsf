<?php

namespace Tests\Feature;

use Tests\TestCase;

class DynamicShopUpperResponsiveTest extends TestCase
{
    public function test_desktop_search_contract_remains_horizontal(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $shopStart = strpos($css, '.shop-template {');
        $tabletStart = strpos($css, '@media (max-width: 900px)', $shopStart);
        $desktopShopCss = substr($css, $shopStart, $tabletStart - $shopStart);

        $this->assertStringContainsString('grid-template-columns: 170px 255px minmax(320px, 1fr);', $desktopShopCss);
        $this->assertStringContainsString('@apply m-0 min-h-[72px] rounded-none;', $desktopShopCss);
        $this->assertStringContainsString('flex: 0 0 calc((100% - 3.4rem) / 5);', $desktopShopCss);
    }

    public function test_tablet_search_wraps_into_one_full_width_input_and_two_controls(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 900px\).*?\.shop-template__search\s*\{[^}]*grid-template-columns:\s*repeat\(2, minmax\(0, 1fr\)\);[^}]*width:\s*100%;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__search-field\s*\{[^}]*grid-column:\s*1 \/ -1;[^}]*grid-row:\s*1;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__select-field\s*\{[^}]*grid-column:\s*1;[^}]*grid-row:\s*2;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__search button\s*\{[^}]*grid-column:\s*2;[^}]*grid-row:\s*2;[^}]*width:\s*100%;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__search button,\s*\.shop-template__search input,\s*\.shop-template__search select\s*\{[^}]*height:\s*auto;[^}]*min-height:\s*44px;/s',
            $css,
        );
    }

    public function test_mobile_categories_are_compact_native_rtl_scroller(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\).*?\.shop-template__hero\s*\{[^}]*min-height:\s*0;[^}]*padding:\s*2rem 1rem 4\.5rem;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__categories\s*\{[^}]*min-height:\s*0;[^}]*padding:\s*\.875rem 0;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__category-row\s*\{[^}]*direction:\s*rtl;[^}]*flex-wrap:\s*nowrap;[^}]*width:\s*max-content;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__category-viewport\s*\{[^}]*direction:\s*rtl;[^}]*overflow-x:\s*auto;[^}]*overflow-y:\s*hidden;[^}]*touch-action:\s*pan-x;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__category\s*\{[^}]*flex:\s*0 0 clamp\(6rem, 28vw, 7\.5rem\);[^}]*min-height:\s*0;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.shop-template__category-media\s*\{[^}]*aspect-ratio:\s*1 \/ 1;[^}]*min-height:\s*0;[^}]*width:\s*3\.5rem;/s',
            $css,
        );
        $this->assertStringContainsString('-webkit-overflow-scrolling: touch;', $css);
    }
}
