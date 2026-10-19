<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\SiteHeader\SiteHeaderBlock;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Resources\TemplateResource;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use App\Services\MenuService;
use App\Services\MenuTreeService;
use App\Services\SettingsService;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteHeaderTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_ID = '01JHEADER00000000000000000';

    public function test_selector_lists_only_published_site_header_templates(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $published = $this->template(status: 'published');
        $draft = $this->template('draft-header', 'site_header', 'draft');
        $otherTarget = $this->template('published-page', 'page', 'published');
        $component = Livewire::test(ManageSiteSettings::class);
        $selector = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn ($field): bool => $field instanceof Select
                && $field->getName() === 'header_template_id');

        $this->assertInstanceOf(Select::class, $selector);
        $this->assertSame(
            [$published->getKey() => $published->title],
            $selector->getOptions(),
        );
        $this->assertArrayNotHasKey($draft->getKey(), $selector->getOptions());
        $this->assertArrayNotHasKey($otherTarget->getKey(), $selector->getOptions());

        $component
            ->set('data.site_name', 'سازه ایرانی')
            ->set('data.header_template_id', $published->getKey())
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('settings', [
            'key' => 'header_template_id',
            'value' => (string) $published->getKey(),
            'group' => 'header',
            'type' => 'select',
        ]);
    }

    public function test_registered_header_block_is_available_alongside_general_blocks(): void
    {
        $block = app(BlockRegistry::class)->find('site_header');
        $definitions = $this->invokeBlockDefinitions('site_header');
        $pickers = collect($block->filamentSchema(HeroBlock::CONTEXT_TEMPLATE))
            ->filter(fn ($component): bool => $component instanceof ActionPicker);

        $this->assertInstanceOf(SiteHeaderBlock::class, $block);
        $keys = array_map(fn ($definition): string => $definition->getName(), $definitions);
        $this->assertContains('site_header', $keys);
        $this->assertContains('cta', $keys);
        $this->assertNotContains('site_footer', $keys);
        $this->assertNotContains('product_header', $keys);
        $this->assertCount(4, $pickers);
        $secondaryPicker = $pickers->first(fn (ActionPicker $picker): bool => $picker->getStatePath(false) === 'content.secondary_action.action');
        $this->assertInstanceOf(ActionPicker::class, $secondaryPicker);
        $this->assertFalse($secondaryPicker->isRequired());
        $this->assertTrue($secondaryPicker->allowsNewTab());

        foreach ($pickers as $picker) {
            $this->assertSame([
                'custom_url',
                'page',
                'project',
                'product',
                'service',
                'form',
                'anchor',
                'email',
                'phone',
            ], array_keys($picker->getTypeOptions()));
        }
    }

    public function test_selected_template_renders_navigation_and_runtime_resolved_actions(): void
    {
        $this->home();
        $target = Page::factory()->published()->create([
            'slug' => 'construction-estimate',
            'title' => 'برآورد ساخت',
        ]);
        $menu = $this->menu();
        $template = $this->template(
            blocks: $this->headerBlocks($target->getKey(), $menu->getKey()),
        );
        $this->select($template);
        $stored = json_encode($template->blocks, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('/construction-estimate', $stored);
        $this->assertStringContainsString('"reference_id":'.$target->getKey(), $stored);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('industrial-header', false)
            ->assertDontSee('industrial-header__topbar', false)
            ->assertDontSee('راهکارهای تخصصی ساخت‌وساز')
            ->assertSee('خدمات و پشتیبانی')
            ->assertSee('همکاری با ما')
            ->assertSee('محاسبه هزینه ساخت')
            ->assertSee('data-desktop-navigation', false)
            ->assertSee('data-navigation-more', false)
            ->assertSee('data-navigation-more-trigger', false)
            ->assertSee('بیشتر')
            ->assertSee($target->resolveNavigationUrl(), false)
            ->assertSee('پروژه‌ها')
            ->assertSee('/projects', false)
            ->assertSee(route('search.index', absolute: false), false);
    }

    public function test_secondary_header_action_resolves_its_reference_and_hides_unavailable_destinations(): void
    {
        $this->home();
        $primary = Page::factory()->published()->create(['slug' => 'primary-destination']);
        $secondary = Page::factory()->published()->create(['slug' => 'secondary-destination']);
        $blocks = $this->headerBlocks($primary->id);
        $blocks[0]['data']['content']['secondary_action'] = [
            'label' => 'درخواست مشاوره',
            'action' => [
                'schema_version' => 1, 'type' => 'page', 'reference_id' => $secondary->id,
                'open_in_new_tab' => true,
            ],
        ];
        $this->select($this->template(blocks: $blocks));

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/<div class="industrial-header__actions">\s*<a\s+class="industrial-header__primary-action"[^>]*href="\/primary-destination"[^>]*>.*?<\/a>\s*<a\s+class="industrial-header__secondary-action"[^>]*href="\/secondary-destination"[^>]*target="_blank"[^>]*rel="noopener noreferrer"[^>]*>درخواست مشاوره<\/a>/s',
            $html,
        );
        $secondary->update(['slug' => 'updated-secondary']);
        $this->get(route('home'))->assertOk()->assertSee('href="/updated-secondary"', false);

        $secondary->update(['status' => 'draft']);
        $this->get(route('home'))->assertOk()
            ->assertDontSee('class="industrial-header__secondary-action"', false)
            ->assertDontSee('class="industrial-header__actions"', false)
            ->assertSee('href="/primary-destination"', false);

        $secondary->delete();
        $this->get(route('home'))->assertOk()
            ->assertDontSee('درخواست مشاوره')
            ->assertDontSee('class="industrial-header__actions"', false)
            ->assertSee('class="industrial-header__primary-action"', false);
    }

    public function test_incomplete_secondary_header_actions_keep_the_legacy_header_markup(): void
    {
        $this->home();
        $primary = Page::factory()->published()->create(['slug' => 'primary-destination']);
        $blocks = $this->headerBlocks($primary->id);
        $template = $this->template(blocks: $blocks);
        $this->select($template);
        $baselineHtml = $this->get(route('home'))->assertOk()->getContent();
        preg_match('/<header\b.*?<\/header>/s', $baselineHtml, $baseline);
        $this->assertNotEmpty($baseline);
        $this->assertStringNotContainsString('class="industrial-header__actions"', $baseline[0]);

        foreach ([
            ['label' => 'مشاوره'],
            ['action' => ['type' => 'custom_url', 'value' => '/contact']],
            ['label' => '   ', 'action' => ['type' => 'custom_url', 'value' => '/contact']],
            ['label' => 'مشاوره', 'action' => ['type' => 'custom_url', 'value' => 'javascript:alert(1)']],
            ['label' => 'مشاوره', 'action' => ['type' => 'page', 'reference_id' => 999999]],
        ] as $secondary) {
            $blocks[0]['data']['content']['secondary_action'] = $secondary;
            $template->update(['blocks' => $blocks]);
            $html = $this->get(route('home'))->assertOk()->getContent();
            preg_match('/<header\b.*?<\/header>/s', $html, $actual);
            $this->assertSame($baseline[0], $actual[0]);
        }
    }

    public function test_industrial_header_renders_nested_services_with_independent_menu_parenting_and_mobile_controls(): void
    {
        $this->home();
        $menu = $this->menu();
        $catalogParent = Service::query()->create([
            'name' => 'Catalog parent', 'slug' => 'catalog-parent', 'status' => Service::STATUS_PUBLISHED,
        ]);
        $archive = $menu->items()->create([
            'title' => 'Services archive', 'type' => MenuItem::TYPE_SOURCE,
            'source_key' => 'services.archive', 'status' => 'active', 'target' => '_self',
        ]);
        $serviceItems = [];
        foreach (['engineering', 'nested-service', 'deep-service'] as $slug) {
            $service = Service::query()->create([
                'name' => $slug, 'slug' => $slug, 'status' => Service::STATUS_PUBLISHED,
                'parent_id' => $catalogParent->id,
            ]);
            $serviceItems[] = $menu->items()->create([
                'title' => $slug, 'type' => MenuItem::TYPE_SERVICE,
                'reference_id' => $service->id, 'reference_type' => $service->getMorphClass(),
                'status' => 'active', 'target' => '_blank',
            ]);
        }
        [$engineering, $nested, $deep] = $serviceItems;
        app(MenuTreeService::class)->save($menu, [
            ['id' => $menu->items()->where('url', '/projects')->sole()->id, 'children' => []],
            ['id' => $archive->id, 'children' => [
                ['id' => $engineering->id, 'children' => [
                    ['id' => $nested->id, 'children' => [
                        ['id' => $deep->id, 'children' => []],
                    ]],
                ]],
            ]],
        ]);

        $this->assertSame($engineering->id, $nested->fresh()->parent_id);
        $managed = collect(app(MenuTreeService::class)->tree($menu))->firstWhere('id', $archive->id);
        $this->assertTrue($managed['children'][0]['children'][0]['destination_available']);
        $public = app(MenuService::class)->byId($menu->id)->rootItems->firstWhere('id', $archive->id);
        $this->assertSame($deep->id, $public->children[0]->children[0]->children[0]->id);
        $this->select($this->template(blocks: $this->headerBlocks(menuId: $menu->id)));

        $html = $this->get(route('home'))->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);
        $navigation = '//nav[contains(@class, "industrial-header__navigation")]';
        $this->assertSame(1, $xpath->query($navigation.'//a[@href="/services"]/following-sibling::ul/li/a[@href="/services/engineering"]/following-sibling::ul/li/a[@href="/services/nested-service"]/following-sibling::ul/li/a[@href="/services/deep-service"]')->length);
        $this->assertSame(1, $xpath->query($navigation.'//a[@href="/projects"]')->length);
        $toggles = $xpath->query($navigation.'//button[@data-industrial-submenu-toggle]');
        $this->assertCount(3, $toggles);
        foreach ($toggles as $toggle) {
            $id = $toggle->getAttribute('aria-controls');
            $this->assertSame('false', $toggle->getAttribute('aria-expanded'));
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]')->length);
            $this->assertSame(1, $xpath->query('//*[@id="'.$id.'"]/preceding-sibling::button[@aria-controls="'.$id.'"]')->length);
        }
        $this->assertSame(3, $xpath->query($navigation.'//a[@target="_blank" and @rel="noopener noreferrer"]')->length);
    }

    public function test_industrial_header_visual_contract_keeps_desktop_navigation_single_line(): void
    {
        $blade = file_get_contents(resource_path('views/partials/blocks/site-header-industrial.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringNotContainsString('industrial-header__topbar', $blade);
        $this->assertStringNotContainsString('راهکارهای تخصصی ساخت‌وساز', $blade);
        $this->assertStringContainsString('flex-wrap: nowrap;', $css);
        $this->assertStringContainsString('@media (max-width: 1359px) and (min-width: 1200px)', $css);
        $this->assertStringContainsString('@media (max-width: 1199px)', $css);
        $this->assertStringContainsString('navigation.scrollWidth > navigation.clientWidth', $javascript);
        $this->assertStringContainsString('moreItems.prepend', $javascript);
        $this->assertStringContainsString("window.getComputedStyle(toggle).display !== 'none'", $javascript);
        $this->assertStringContainsString("event.key === 'Escape'", $javascript);
        $this->assertStringContainsString('moreTrigger.focus()', $javascript);
    }

    public function test_industrial_header_overflow_parent_uses_a_nested_flyout_without_replacing_siblings(): void
    {
        $blade = file_get_contents(resource_path('views/partials/blocks/site-header-industrial.blade.php'));
        $css = file_get_contents(resource_path('css/app.css'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('<ul data-navigation-more-items></ul>', $blade);
        $this->assertStringContainsString('moreItems.prepend(candidates().at(-1))', $javascript);
        $this->assertStringContainsString("navigation.addEventListener('pointerover', positionEventSubmenu)", $javascript);
        $this->assertStringContainsString("navigation.addEventListener('focusin', positionEventSubmenu)", $javascript);
        $this->assertStringContainsString("item.querySelector(':scope > ul')", $javascript);
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__navigation li li\s*>\s*ul\s*\{[^}]*left:\s*auto;[^}]*right:\s*100%;[^}]*top:\s*0;[^}]*z-index:\s*31;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__navigation li li\.is-submenu-inline-end\s*>\s*ul\s*\{[^}]*left:\s*100%;[^}]*right:\s*auto;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__navigation > ul > li > ul\s*\{[^}]*right:\s*0;[^}]*top:\s*100%;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__navigation li > ul\s*\{[^}]*margin:\s*0;[^}]*overflow:\s*visible;[^}]*position:\s*absolute;/s',
            $css,
        );
    }

    public function test_industrial_header_dropdown_uses_canonical_typography_and_content_width(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.industrial-header__navigation li\s*>\s*ul\s*\{[^}]*font-size:\s*var\(--theme-base-font-size, 16px\);[^}]*max-width:\s*calc\(100vw - 2rem\);[^}]*width:\s*max-content;/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__navigation li\s*>\s*ul a\s*\{[^}]*font-size:\s*var\(--theme-base-font-size, 16px\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__navigation li ul a\s*\{[^}]*font-size:\s*var\(--theme-base-font-size-mobile, 15px\);/s',
            $css,
        );
    }

    public function test_industrial_header_actions_use_the_canonical_theme_palette(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression(
            '/\.industrial-header__top-action\s*\{[^}]*background:\s*var\(--theme-secondary\);[^}]*color:\s*var\(--theme-background\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__top-actions\s*>\s*:first-child[^}]*\{\s*background:\s*var\(--theme-primary\);/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__primary-action\s*\{[^}]*background:\s*var\(--theme-primary\);[^}]*color:\s*var\(--theme-background\);/s',
            $css,
        );
        $this->assertStringContainsString('background: var(--theme-primary-hover);', $css);
        $this->assertStringContainsString('outline: 3px solid var(--theme-link);', $css);
        $this->assertDoesNotMatchRegularExpression(
            '/\.industrial-header__(?:top-action|primary-action)[^{]*\{[^}]*(?:#b91c1c|#991b1b)/s',
            $css,
        );
        $this->assertMatchesRegularExpression(
            '/\.industrial-header__navigation\s*>\s*ul\s*>\s*li\s*>\s*a::before\s*\{[^}]*background:\s*currentColor;/s',
            $css,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.industrial-header__navigation\s*>\s*ul\s*>\s*li\s*>\s*a::before\s*\{[^}]*background:\s*#b91c1c;/s',
            $css,
        );
    }

    public function test_industrial_header_mobile_drawer_and_sticky_actions_contract(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('.industrial-header.is-top-actions-hidden', $css);
        $this->assertStringContainsString('height: calc(100dvh - 70px);', $css);
        $this->assertStringContainsString('position: fixed;', $css);
        $this->assertStringContainsString('body.industrial-mobile-menu-open', $css);
        $this->assertStringContainsString("document.body.classList.add('industrial-mobile-menu-open')", $javascript);
        $this->assertStringContainsString("document.body.classList.remove('industrial-mobile-menu-open')", $javascript);
        $this->assertStringContainsString("toggle.setAttribute('aria-expanded', 'true')", $javascript);
        $this->assertStringContainsString('toggle.focus()', $javascript);
        $this->assertStringContainsString('initIndustrialStickyHeader', $javascript);

        $stickyHeader = file_get_contents(resource_path('js/components/industrial-sticky-header.js'));

        $this->assertStringContainsString('INDUSTRIAL_HEADER_HIDE_THRESHOLD = 106', $stickyHeader);
        $this->assertStringContainsString('INDUSTRIAL_HEADER_SHOW_THRESHOLD = 5', $stickyHeader);
        $this->assertStringNotContainsString('lastScrollY', $stickyHeader);
        $this->assertStringContainsString("header.classList.toggle('is-top-actions-hidden', hidden)", $stickyHeader);
        $this->assertStringContainsString("window.addEventListener('scroll', scheduleUpdate, { passive: true })", $stickyHeader);
        $this->assertStringContainsString("window.addEventListener('pageshow', scheduleUpdate)", $stickyHeader);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
    }

    public function test_disabled_shop_does_not_render_industrial_header_cart(): void
    {
        $this->home();
        $template = $this->template(blocks: $this->headerBlocks());
        $this->select($template);
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('industrial-header__cart', $html);
        $this->assertStringNotContainsString('header-cart-drawer', $html);
        $this->assertStringNotContainsString('industrial-header__cart-badge', $html);
        $this->assertStringNotContainsString(route('cart.index', absolute: false), $html);
        $this->assertStringContainsString('industrial-header__search', $html);
    }

    public function test_enabled_shop_renders_cart_without_badge_when_cart_is_empty(): void
    {
        $this->home();
        $template = $this->template(blocks: $this->headerBlocks());
        $this->select($template);
        app(SettingsService::class)->set('shop_enabled', true, 'shop', 'boolean');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('industrial-header__cart', false)
            ->assertSee('aria-label="سبد خرید"', false)
            ->assertSee('data-header-overlay-trigger', false)
            ->assertSee('header-cart-drawer', false)
            ->assertSee('سبد خرید شما خالی است.')
            ->assertSee('href="'.route('shop.index', absolute: false).'"', false)
            ->assertDontSee('industrial-header__cart-badge', false);
    }

    public function test_industrial_header_guest_uses_one_canonical_login_icon(): void
    {
        $this->home();
        $template = $this->template(blocks: $this->headerBlocks());
        $this->select($template);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('public-account-controls--icon-only', $html);
        $this->assertStringContainsString('public-account-controls__guest-icon', $html);
        $this->assertStringContainsString('href="'.route('login').'"', $html);
        $this->assertStringContainsString('aria-label="ورود به حساب کاربری"', $html);
        $this->assertStringNotContainsString('public-account-controls__register', $html);
        $this->assertStringNotContainsString('public-account-menu__trigger', $html);
        $this->assertStringNotContainsString('public-account-menu__dropdown', $html);
    }

    public function test_authenticated_industrial_header_keeps_account_dropdown_and_cart(): void
    {
        $this->home();
        $template = $this->template(blocks: $this->headerBlocks());
        $this->select($template);
        $user = User::factory()->client()->create(['name' => 'کاربر هدر']);

        $html = $this->actingAs($user, 'client')
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('public-account-menu__icon', $html);
        $this->assertStringContainsString('کاربر هدر', $html);
        $this->assertStringContainsString('public-account-menu__trigger', $html);
        $this->assertStringContainsString('public-account-menu__dropdown', $html);
        $this->assertStringContainsString('industrial-header__cart', $html);
        $this->assertStringNotContainsString('public-account-controls__guest-icon', $html);
        $this->assertStringNotContainsString('public-account-controls__register', $html);
    }

    public function test_industrial_header_cart_badge_uses_total_quantity_and_caps_display(): void
    {
        $this->home();
        $template = $this->template(blocks: $this->headerBlocks());
        $this->select($template);
        app(SettingsService::class)->set('shop_enabled', true, 'shop', 'boolean');

        $cart = static fn (int $first, int $second): array => [
            ['product_id' => 10, 'title' => 'محصول اول', 'slug' => 'first', 'image' => null, 'quantity' => $first, 'unit_price' => 100],
            ['product_id' => 20, 'title' => 'محصول دوم', 'slug' => 'second', 'image' => null, 'quantity' => $second, 'unit_price' => 200],
        ];

        $html = $this->withSession(['shop_cart' => $cart(2, 3)])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="سبد خرید، 5 کالا"', false)
            ->assertSee('industrial-header__cart-badge', false)
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/industrial-header__cart-badge[^>]*>\s*5\s*<\/span>/',
            $html,
        );
        $this->assertStringContainsString('محصول اول', $html);
        $this->assertStringContainsString('2 × 100 تومان', $html);
        $this->assertStringContainsString('800 تومان', $html);
        $this->assertStringContainsString('href="'.route('cart.index', absolute: false).'"', $html);
        $this->assertStringContainsString('href="'.route('checkout.create', absolute: false).'"', $html);
        $this->assertStringContainsString('action="'.route('cart.remove', absolute: false).'"', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('name="product_id" value="10"', $html);
        $this->assertStringContainsString('aria-label="حذف محصول اول از سبد خرید"', $html);
        $this->assertStringContainsString('class="icon-trash"', $html);
        $this->assertStringContainsString('data-cart-drawer-remove', $html);
        $this->assertStringContainsString('data-cart-item="10"', $html);
        $this->assertStringContainsString('data-cart-subtotal', $html);
        $this->assertStringContainsString('data-cart-subtotal-block', $html);
        $this->assertStringContainsString('data-cart-actions', $html);
        $this->assertStringContainsString('data-cart-empty-state', $html);
        $this->assertStringContainsString('data-cart-footer', $html);
        $this->assertStringContainsString('data-cart-trigger', $html);
        $this->assertStringContainsString('data-cart-badge', $html);

        $this->withSession(['shop_cart' => $cart(60, 50)])
            ->get(route('home'))
            ->assertOk()
            ->assertSee('aria-label="سبد خرید، 110 کالا"', false)
            ->assertSee('99+', false);
    }

    public function test_industrial_header_search_uses_shared_global_search_contract(): void
    {
        $this->home();
        $template = $this->template(blocks: $this->headerBlocks());
        $this->select($template);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('industrial-header__search', $html);
        $this->assertStringContainsString('aria-haspopup="dialog"', $html);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('header-search-modal', $html);
        $this->assertStringContainsString('method="get" action="'.route('search.index', absolute: false).'"', $html);
        $this->assertStringContainsString('name="q"', $html);
        $this->assertStringContainsString('data-header-overlay-autofocus', $html);
        $this->assertStringContainsString('value="product"', $html);
        $this->assertStringContainsString('value="project"', $html);
        $this->assertStringContainsString('value="service"', $html);
        $this->assertStringContainsString('value="post"', $html);
        $this->assertStringNotContainsString('جستجوی نوشته‌ها', $html);
        $this->assertStringContainsString('icon-arrow-circle-left fhai', $html);
        $this->assertStringContainsString('icon-arrow-circle-right', $html);
        $this->assertStringContainsString('aria-controls="industrial-navigation-', $html);

        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertMatchesRegularExpression(
            '/\.search-scope-option input:checked \+ span\s*\{[^}]*background:\s*var\(--theme-primary/s',
            $css,
        );
        $this->assertStringContainsString('height: 64px;', $css);
    }

    public function test_missing_draft_and_unavailable_actions_fail_closed_to_legacy_header(): void
    {
        $this->home();
        $draftTarget = Page::factory()->draft()->create();
        $draftTemplate = $this->template(
            status: 'draft',
            blocks: $this->headerBlocks($draftTarget->getKey()),
        );
        $this->select($draftTemplate);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-site-header', false)
            ->assertDontSee('industrial-header', false)
            ->assertDontSee('محاسبه هزینه ساخت');

        $publishedTemplate = $this->template(
            'published-unavailable-action',
            blocks: $this->headerBlocks($draftTarget->getKey()),
        );
        $this->select($publishedTemplate);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('industrial-header', false)
            ->assertDontSee('محاسبه هزینه ساخت')
            ->assertSee('سایت آزمایشی');

        Setting::query()->where('key', 'header_template_id')->update(['value' => '999999']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('industrial-header', false)
            ->assertSee('data-site-header', false);
    }

    public function test_no_selection_keeps_legacy_header_even_when_a_default_template_exists(): void
    {
        $this->home();
        $this->template(
            blocks: $this->headerBlocks(),
            isDefault: true,
        );

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-site-header', false)
            ->assertDontSee('industrial-header', false)
            ->assertSee('تماس با ما');
    }

    public function test_navigation_sources_remain_fail_closed_and_mobile_controls_are_unique(): void
    {
        $this->home();
        $target = Page::factory()->published()->create();
        $menu = $this->menu(includeUnavailableSource: true);
        $template = $this->template(
            blocks: $this->headerBlocks($target->getKey(), $menu->getKey()),
        );
        $this->select($template);
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('پروژه‌ها', $html);
        $this->assertStringNotContainsString('منبع ناموجود', $html);
        $this->assertSame(1, substr_count($html, 'data-menu-toggle'));
        $this->assertSame(1, substr_count($html, 'data-site-nav'));
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-label="باز کردن منوی اصلی"', $html);
    }

    public function test_draft_header_template_preview_uses_the_same_header_contract_once(): void
    {
        $this->home();
        $target = Page::factory()->published()->create();
        $template = $this->template(
            'preview-industrial-header',
            status: 'draft',
            blocks: $this->headerBlocks($target->getKey()),
        );

        $html = $this
            ->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.templates.show', $template))
            ->assertOk()
            ->assertSee('پیش‌نمایش هدر در بالای همین صفحه نمایش داده شده است.')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'class="site-header industrial-header'));
        $this->assertStringContainsString('محاسبه هزینه ساخت', $html);
    }

    public function test_database_seed_provides_the_first_published_selected_header(): void
    {
        $this->seed();
        $template = Template::query()
            ->where('slug', 'industrial-header-v1')
            ->sole();
        $setting = Setting::query()
            ->where('key', 'header_template_id')
            ->sole();
        $stored = json_encode($template->blocks, JSON_THROW_ON_ERROR);

        $this->assertSame('هدر صنعتی دو سطحی', $template->title);
        $this->assertSame('site_header', $template->type);
        $this->assertSame('published', $template->status);
        $this->assertSame((string) $template->getKey(), $setting->value);
        $this->assertStringContainsString('"type":"page"', $stored);
        $this->assertStringContainsString('"reference_id":', $stored);
        $this->assertStringNotContainsString('button_url', $stored);
        $this->assertStringNotContainsString('cta_url', $stored);
    }

    private function home(): Page
    {
        Setting::query()->updateOrCreate(
            ['key' => 'site_name'],
            ['value' => 'سایت آزمایشی', 'group' => 'general', 'type' => 'text'],
        );

        return Page::factory()->published()->create([
            'slug' => 'home',
            'title' => 'خانه',
            'blocks' => [],
        ]);
    }

    private function template(
        string $slug = 'industrial-header-v1',
        string $type = 'site_header',
        string $status = 'published',
        array $blocks = [],
        bool $isDefault = false,
    ): Template {
        return Template::query()->create([
            'title' => $slug === 'industrial-header-v1'
                ? 'هدر صنعتی دو سطحی'
                : $slug,
            'slug' => $slug,
            'type' => $type,
            'status' => $status,
            'is_default' => $isDefault,
            'conditions' => ['type' => 'all'],
            'blocks' => $blocks,
        ]);
    }

    private function select(Template $template): void
    {
        Setting::query()->updateOrCreate(
            ['key' => 'header_template_id'],
            [
                'value' => (string) $template->getKey(),
                'group' => 'header',
                'type' => 'select',
            ],
        );
    }

    private function menu(bool $includeUnavailableSource = false): Menu
    {
        $menu = Menu::query()->create([
            'title' => 'منوی اصلی',
            'slug' => 'industrial-main',
            'location' => 'main',
            'status' => 'active',
        ]);
        MenuItem::query()->create([
            'menu_id' => $menu->getKey(),
            'title' => 'پروژه‌ها',
            'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => '/projects',
            'target' => '_self',
            'sort_order' => 1,
            'status' => 'active',
        ]);

        if ($includeUnavailableSource) {
            MenuItem::query()->create([
                'menu_id' => $menu->getKey(),
                'title' => 'منبع ناموجود',
                'type' => MenuItem::TYPE_SOURCE,
                'source_key' => 'unknown.source',
                'target' => '_self',
                'sort_order' => 2,
                'status' => 'active',
            ]);
        }

        return $menu;
    }

    private function headerBlocks(?int $referenceId = null, ?int $menuId = null): array
    {
        $action = $referenceId
            ? [
                'schema_version' => 1,
                'type' => 'page',
                'reference_id' => $referenceId,
                'open_in_new_tab' => false,
            ]
            : null;

        return [[
            'type' => 'site_header',
            'data' => [
                'block_id' => self::BLOCK_ID,
                'schema_version' => 1,
                'template' => 'industrial-header-v1',
                'content' => [
                    'top_actions' => [
                        ['label' => 'خدمات و پشتیبانی', 'action' => $action],
                        ['label' => 'همکاری با ما', 'action' => $action],
                    ],
                    'primary_action' => [
                        'label' => 'محاسبه هزینه ساخت',
                        'action' => $action,
                    ],
                ],
                'settings' => [
                    'menu_id' => $menuId,
                    'search_enabled' => true,
                    'sticky_enabled' => true,
                    'top_bar_enabled' => true,
                ],
            ],
        ]];
    }

    private function invokeBlockDefinitions(string $target): array
    {
        $method = new \ReflectionMethod(TemplateResource::class, 'blockDefinitions');

        return $method->invoke(null, $target);
    }
}
