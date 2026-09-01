<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Template;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicShopTypographyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_shop_uses_the_existing_desktop_and_mobile_typography_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $shopStart = strpos($css, '.shop-template {');
        $shopEnd = strpos($css, '.project-detail h1', $shopStart);
        $shopCss = substr($css, $shopStart, $shopEnd - $shopStart);

        $this->assertStringContainsString('font-size: var(--theme-base-font-size, 16px);', $shopCss);
        $this->assertStringContainsString('font-size: var(--theme-button-font-size, 16px);', $shopCss);
        $this->assertStringContainsString('font-size: var(--theme-h1-font-size, 24px);', $shopCss);
        $this->assertStringContainsString('font-size: var(--theme-h4-font-size, 18px);', $shopCss);

        foreach ([
            'clamp(2.2rem, 4vw, 3.55rem)',
            'text-[.86rem]',
            'text-[1.08rem]',
            'text-[.88rem]',
            'text-[.9rem]',
            'text-[.84rem]',
            'text-[.78rem]',
            'text-[.98rem]',
            'text-[.85rem]',
        ] as $legacySize) {
            $this->assertStringNotContainsString($legacySize, $shopCss);
        }

        $this->assertStringNotContainsString('m-0 text-[1.35rem] font-black', $shopCss);

        $this->assertDoesNotMatchRegularExpression('/font-size:\s*[^;]+!important/', $shopCss);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\).*?\.shop-template\s*\{[^}]*font-size:\s*var\(--theme-base-font-size-mobile, 15px\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\).*?\.shop-template__favorites-filter,\s*\.shop-template__filter-toggle\s*\{[^}]*font-size:\s*var\(--theme-button-font-size-mobile, 15px\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\).*?\.shop-template__category-media span\s*\{[^}]*font-size:\s*var\(--theme-h1-font-size-mobile, 22px\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 560px\).*?\.shop-template__price\s*\{[^}]*font-size:\s*var\(--theme-h4-font-size-mobile, 16px\);/s',
            $css,
        );
    }

    public function test_saved_site_typography_values_drive_dynamic_shop_without_reseeding(): void
    {
        $settings = app(SettingsService::class);
        $values = [
            'base_font_size' => '19px',
            'button_font_size' => '17px',
            'h1_font_size' => '41px',
            'h2_font_size' => '33px',
            'h3_font_size' => '27px',
            'h4_font_size' => '23px',
            'base_font_size_mobile' => '14px',
            'button_font_size_mobile' => '13px',
            'h1_font_size_mobile' => '31px',
            'h2_font_size_mobile' => '26px',
            'h3_font_size_mobile' => '22px',
            'h4_font_size_mobile' => '18px',
        ];

        foreach ($values as $key => $value) {
            $settings->set($key, $value, 'theme', 'text');
        }

        Product::factory()->published()->create(['title' => 'Typography Product']);
        Template::query()->create([
            'title' => 'Typography Shop',
            'slug' => 'typography-shop',
            'type' => 'shop_index',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [[
                'type' => 'template_shop_complete',
                'data' => ['title' => 'Typography Shop'],
            ]],
        ]);

        $response = $this->get(route('shop.index'))->assertOk()->assertSee('Typography Product');

        foreach (app(SettingsService::class)->themeVariables() as $variable => $value) {
            if (str_contains($variable, 'font-size')) {
                $response->assertSee("{$variable}: {$value};", false);
            }
        }

        $settings->set('base_font_size', '21px', 'theme', 'text');

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('--theme-base-font-size: 21px;', false);
    }

    public function test_complete_shop_seed_carries_no_site_specific_typography_values(): void
    {
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $start = strpos($source, "['slug' => 'complete-shop-page-template']");
        $end = strpos($source, "'conditions' => ['type' => 'all']", $start);
        $shopSeed = substr($source, $start, $end - $start);

        $this->assertStringNotContainsString('font_size', $shopSeed);
        $this->assertStringNotContainsString('font-size', $shopSeed);
        $this->assertStringNotContainsString('typography', strtolower($shopSeed));
    }
}
