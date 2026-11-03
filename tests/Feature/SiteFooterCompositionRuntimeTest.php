<?php

namespace Tests\Feature;

use App\CMS\Blocks\SiteFooter\SiteFooterRuntime;
use App\CMS\Navigation\NavigationSource;
use App\CMS\Navigation\NavigationSourceRegistry;
use App\Models\Form;
use App\Models\Media;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SiteFooterCompositionRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_html_is_byte_for_byte_identical_to_the_pre_phase_2_snapshot(): void
    {
        $footer = $this->fixture('legacy-presentation');
        $this->assertSame(file_get_contents(__DIR__.'/../Fixtures/site-footer/legacy-output.html'), $this->render($footer));
    }

    public function test_site_data_stays_dynamic_and_manual_and_field_overrides_follow_the_v2_contract(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/logo.png', UploadedFile::fake()->image('logo.png')->getContent());
        $this->settings([
            'site_name' => 'نام زنده', 'site_logo' => 'settings/logo.png', 'footer_text' => 'معرفی زنده',
            'contact_phone' => '۰۲۱ ۱۲۳۴۵۶۷۸', 'contact_mobile' => '09121234567', 'contact_email' => 'hello@example.test',
            'contact_address' => 'آدرس سایت', 'working_hours' => '۸ تا ۱۷', 'social_instagram_url' => 'https://instagram.com/example',
        ]);
        $data = $this->data([
            $this->element('branding'), $this->element('rich_text'), $this->element('contact'), $this->element('social_links'),
            $this->element('branding', ['source' => 'manual', 'name' => 'نام مستقل']),
            $this->element('contact', ['source' => 'override', 'fields' => ['email', 'phone', 'address'], 'overrides' => ['phone' => '02199999999', 'email' => null]]),
        ], [$this->element('copyright')]);
        $before = $data;
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        $elements = $prepared['composition']['main']['columns'][0]['elements'];
        $this->assertSame('نام زنده', $elements[0]['name']);
        $this->assertStringContainsString('/storage/settings/logo.png', $elements[0]['logo']['url']);
        $this->assertStringContainsString('معرفی زنده', $elements[1]['html']->toHtml());
        $this->assertSame('tel:02112345678', $elements[2]['contacts'][0]['presentation']['href']);
        $this->assertCount(5, $elements[2]['contacts']);
        $this->assertSame('icon-instagram', $elements[3]['items'][0]['icon']);
        $this->assertSame('نام مستقل', $elements[4]['name']);
        $this->assertNull($elements[4]['logo']);
        $this->assertSame(['email', 'phone', 'address'], array_column($elements[5]['contacts'], 'type'));
        $this->assertSame('tel:02199999999', $elements[5]['contacts'][1]['presentation']['href']);
        $this->assertSame('hello@example.test', $elements[5]['contacts'][0]['value']);
        $this->settings(['site_name' => 'نام تازه', 'footer_text' => 'معرفی تازه', 'contact_email' => 'new@example.test']);
        $new = app(SiteFooterRuntime::class)->prepare($data);
        $this->assertSame('نام تازه', $new['composition']['main']['columns'][0]['elements'][0]['name']);
        $this->assertSame('نام تازه', $new['composition']['bottom']['elements'][0]['text']);
        $this->assertSame(now()->year, $new['composition']['bottom']['elements'][0]['year']);
        $this->assertSame('نام مستقل', $new['composition']['main']['columns'][0]['elements'][4]['name']);
        $this->assertSame($before, $data);
    }

    public function test_empty_disabled_invalid_and_out_of_scope_elements_do_not_leave_columns_or_regions(): void
    {
        $this->settings(['site_name' => 'Valid site', 'contact_phone' => 'invalid', 'contact_email' => 'invalid', 'social_x_url' => 'javascript:alert(1)']);
        $data = $this->data([
            $this->element('branding', ['source' => 'manual']),
            $this->element('rich_text', ['source' => 'manual', 'title' => 'Empty heading', 'text' => '<script>bad()</script>']),
            $this->element('navigation', ['menu_id' => 9999]),
            $this->element('contact', ['fields' => ['phone', 'email']]),
            $this->element('social_links'), $this->element('image', ['media_id' => 9999]),
            $this->element('map', ['title' => 'No map']), $this->element('form', ['form_id' => 99]),
            $this->element('branding', ['name' => 'Disabled'], false),
        ], [$this->element('legal_links'), $this->element('copyright', ['source' => 'manual'])]);
        $data['content']['composition']['main']['columns'][0]['title'] = 'Empty column';
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        $this->assertSame([], $prepared['composition']['main']['columns']);
        $this->assertSame([], $prepared['composition']['bottom']['elements']);
        $this->assertSame('', trim($this->render($prepared)));
    }

    public function test_manual_content_and_overrides_do_not_accidentally_merge_or_snapshot_site_defaults(): void
    {
        Storage::fake('public');
        $media = $this->media('override.png');
        $this->settings([
            'site_name' => 'Inherited name', 'site_logo' => 'settings/missing.png', 'footer_text' => 'Inherited intro',
            'contact_email' => 'site@example.test', 'contact_address' => 'Site address',
            'social_instagram_url' => 'https://instagram.com/site',
        ]);
        $data = $this->data([
            $this->element('branding', ['source' => 'override', 'name' => 'Override name', 'logo_media_id' => $media->id]),
            $this->element('branding'),
            $this->element('rich_text', ['source' => 'manual', 'text' => 'Manual intro']),
            $this->element('rich_text', ['source' => 'override', 'text' => null]),
            $this->element('contact', ['source' => 'manual', 'fields' => ['email', 'address'], 'overrides' => ['address' => 'Manual address']]),
            $this->element('social_links', ['source' => 'override', 'items' => [
                ['label' => 'Manual social', 'icon' => 'unknown-icon', 'action' => ['type' => 'custom_url', 'value' => '/social']],
            ]]),
            $this->element('social_links', ['source' => 'override', 'items' => []]),
        ], [
            $this->element('copyright', ['source' => 'manual', 'text' => 'Manual rights', 'show_year' => false]),
            $this->element('copyright', ['source' => 'override', 'text' => null]),
        ]);
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        $elements = $prepared['composition']['main']['columns'][0]['elements'];
        $this->assertSame('Override name', $elements[0]['name']);
        $this->assertSame($media->getUrl(), $elements[0]['logo']['url']);
        $this->assertNull($elements[1]['logo']);
        $this->assertStringContainsString('Manual intro', $elements[2]['html']->toHtml());
        $this->assertStringContainsString('Inherited intro', $elements[3]['html']->toHtml());
        $this->assertSame(['address'], array_column($elements[4]['contacts'], 'type'));
        $this->assertSame(['Manual social'], array_column($elements[5]['items'], 'label'));
        $this->assertNull($elements[5]['items'][0]['icon']);
        $this->assertSame(['Instagram'], array_column($elements[6]['items'], 'label'));
        $this->assertNull($prepared['composition']['bottom']['elements'][0]['year']);
        $this->assertSame('Inherited name', $prepared['composition']['bottom']['elements'][1]['text']);
    }

    public function test_media_is_loaded_in_one_batch_with_alt_fallback_and_missing_assets_fail_closed(): void
    {
        Storage::fake('public');
        $media = $this->media('trust.png');
        $media->setCustomProperty('alt_text', 'متن رسانه');
        $media->save();
        $data = $this->data([
            $this->element('image', ['media_id' => $media->getKey()]),
            $this->element('branding', ['source' => 'manual', 'logo_media_id' => $media->getKey(), 'logo_alt' => 'لوگوی دستی']),
            $this->element('image', ['media_id' => $media->getKey(), 'alt' => 'Alt دستی']),
        ]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "media"'));
        $this->assertCount(1, $queries);
        $elements = $prepared['composition']['main']['columns'][0]['elements'];
        $this->assertSame('متن رسانه', $elements[0]['image']['alt']);
        $this->assertSame('لوگوی دستی', $elements[1]['logo']['alt']);
        $this->assertSame('Alt دستی', $elements[2]['image']['alt']);
        $this->assertSame(200, $elements[0]['image']['width']);
        $this->assertDatabaseCount('media', 1);
        $media->name = 'New metadata title';
        $media->setCustomProperty('alt_text', 'New metadata alt');
        $media->save();
        $this->assertSame('New metadata alt', app(SiteFooterRuntime::class)->prepare($data)['composition']['main']['columns'][0]['elements'][0]['image']['alt']);
        Storage::disk('public')->delete($media->getPathRelativeToRoot());
        $this->assertSame([], app(SiteFooterRuntime::class)->prepare($data)['composition']['main']['columns']);
    }

    public function test_map_uses_static_media_and_safe_directions_with_a_link_only_fallback(): void
    {
        Storage::fake('public');
        $media = $this->media('map.png');
        $action = ['type' => 'custom_url', 'value' => 'https://example.test/directions', 'open_in_new_tab' => true];
        $data = $this->data([
            $this->element('map', ['media_id' => $media->getKey(), 'directions_action' => $action, 'alt' => 'موقعیت دفتر']),
            $this->element('map', ['media_id' => 9999, 'directions_action' => $action]),
            $this->element('image', ['media_id' => $media->getKey(), 'action' => ['type' => 'page', 'reference_id' => 9999]]),
        ]);
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        $elements = $prepared['composition']['main']['columns'][0]['elements'];
        $this->assertNotNull($elements[0]['image']);
        $this->assertNull($elements[1]['image']);
        $this->assertSame($action['value'], $elements[1]['presentation']['href']);
        $this->assertNull($elements[2]['presentation']);
        $html = $this->render($prepared);
        $this->assertSame(2, substr_count($html, '>مسیریابی</bdi>'));
        $this->assertStringContainsString('noopener', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_non_reusable_images_and_non_image_media_are_not_rendered(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $unshared = $owner->addMedia(UploadedFile::fake()->image('private.png'))->toMediaCollection('featured_image', 'public');
        $pdf = $owner->addMedia(UploadedFile::fake()->createWithContent('file.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"))->toMediaCollection('media_library', 'public');
        $data = $this->data([
            $this->element('image', ['media_id' => $unshared->id]),
            $this->element('image', ['media_id' => $pdf->id]),
        ]);
        $this->assertSame('', trim($this->render(app(SiteFooterRuntime::class)->prepare($data))));
    }

    public function test_eight_columns_and_bottom_elements_render_in_the_persisted_order(): void
    {
        $legal = $this->element('legal_links', ['items' => [['label' => 'Legal', 'action' => ['type' => 'custom_url', 'value' => '/legal']]]]);
        $copyright = $this->element('copyright', ['source' => 'manual', 'text' => 'Rights']);
        $data = $this->data([], [$legal, $copyright]);
        $columns = [];
        foreach (range(1, 8) as $index) {
            $columns[] = ['id' => (string) Str::ulid(), 'title' => null, 'elements' => [
                $this->element('branding', ['source' => 'manual', 'name' => 'Column '.$index]),
            ]];
        }
        $data['content']['composition']['main']['columns'] = array_reverse($columns);
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        $this->assertSame(array_reverse(array_column($columns, 'id')), array_column($prepared['composition']['main']['columns'], 'id'));
        $html = $this->render($prepared);
        $this->assertSame(8, substr_count($html, 'data-footer-column='));
        $this->assertLessThan(strpos($html, 'Column 1'), strpos($html, 'Column 8'));
        $this->assertLessThan(strpos($html, $copyright['id']), strpos($html, $legal['id']));
    }

    public function test_navigation_reuses_core_visibility_sources_order_and_bounded_depth(): void
    {
        app(NavigationSourceRegistry::class)->register(new NavigationSource(
            sourceKey: 'footer.test', label: 'Footer test', module: null, resolver: fn () => '/source-link', availability: fn () => true,
        ));
        $menu = Menu::query()->create(['title' => 'Footer', 'slug' => 'footer', 'status' => 'active']);
        $root = $this->menuItem($menu, ['title' => 'Parent', 'url' => '/parent', 'sort_order' => 2]);
        $child = $this->menuItem($menu, ['title' => 'Child', 'url' => '/child', 'parent_id' => $root->id]);
        $this->menuItem($menu, ['title' => 'Grandchild', 'url' => '/grandchild', 'parent_id' => $child->id]);
        $this->menuItem($menu, ['title' => 'Source', 'type' => MenuItem::TYPE_SOURCE, 'source_key' => 'footer.test', 'url' => null, 'sort_order' => 1]);
        $this->menuItem($menu, ['title' => 'Inactive', 'status' => 'inactive']);
        $this->menuItem($menu, ['title' => 'Unsafe', 'url' => 'javascript:alert(1)']);
        $this->menuItem($menu, ['title' => 'Unknown source', 'type' => MenuItem::TYPE_SOURCE, 'source_key' => 'missing.source', 'url' => null]);
        $draft = Page::factory()->create(['status' => 'draft', 'slug' => 'draft-nav-page']);
        $this->menuItem($menu, ['title' => 'Draft page', 'type' => MenuItem::TYPE_PAGE, 'reference_id' => $draft->id, 'reference_type' => Page::class, 'url' => null]);
        $data = $this->data([
            $this->element('navigation', ['menu_id' => $menu->id, 'depth' => 1]),
            $this->element('navigation', ['menu_id' => $menu->id, 'depth' => 3]),
        ]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        $this->assertCount(1, collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "menus"')));
        $elements = $prepared['composition']['main']['columns'][0]['elements'];
        $this->assertSame(['Source', 'Parent'], array_column($elements[0]['items'], 'label'));
        $this->assertSame([], $elements[0]['items'][1]['children']);
        $this->assertSame('Grandchild', $elements[1]['items'][1]['children'][0]['children'][0]['label']);
        DB::flushQueryLog();
        $html = $this->render($prepared);
        $this->assertSame([], DB::getQueryLog());
        $this->assertStringNotContainsString('Draft page', $html);
        $menu->update(['status' => 'inactive']);
        $this->assertSame([], app(SiteFooterRuntime::class)->prepare($data)['composition']['main']['columns']);
    }

    public function test_actions_respect_production_and_preview_resolution_without_rendering_forms_or_placeholders(): void
    {
        $published = Page::factory()->published()->create(['slug' => 'legal-published']);
        $draft = Page::factory()->create(['status' => 'draft', 'slug' => 'legal-draft']);
        $form = Form::query()->create(['name' => 'Unused form', 'slug' => 'unused-form', 'status' => 'published', 'schema' => []]);
        $data = $this->data([], [$this->element('legal_links', ['items' => [
            ['label' => 'Public', 'action' => ['type' => 'page', 'reference_id' => $published->id]],
            ['label' => 'Draft', 'action' => ['type' => 'page', 'reference_id' => $draft->id]],
            ['label' => 'Missing', 'action' => ['type' => 'page', 'reference_id' => 9999]],
            ['label' => 'Form', 'action' => ['type' => 'form', 'reference_id' => $form->id, 'display' => 'modal']],
            ['label' => 'Placeholder', 'action' => ['type' => 'custom_url', 'value' => '#']],
        ]])]);
        $runtime = app(SiteFooterRuntime::class);
        $public = $runtime->prepare($data);
        $this->assertSame(['Public'], array_column($public['composition']['bottom']['elements'][0]['items'], 'label'));
        $preview = $runtime->prepare($data, preview: true);
        $links = $preview['composition']['bottom']['elements'][0]['items'];
        $this->assertSame(['Public', 'Draft'], array_column($links, 'label'));
        $this->assertSame(route('admin.preview.pages.show', $draft, absolute: false), $links[1]['presentation']['href']);
    }

    public function test_rendering_prepared_data_has_no_queries_one_footer_and_preserves_order_and_accessible_labels(): void
    {
        $this->settings(['site_name' => 'First', 'contact_email' => 'hello@example.test']);
        $first = $this->element('branding');
        $second = $this->element('contact', ['fields' => ['email']]);
        $third = $this->element('rich_text', ['source' => 'manual', 'text' => '<p>Safe <strong>content</strong></p><script>alert(1)</script>']);
        $data = $this->data([$first, $second]);
        $data['content']['composition']['main']['columns'][] = ['id' => (string) Str::ulid(), 'title' => 'Second column', 'elements' => [$third]];
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $html = $this->render($prepared);
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame(1, substr_count($html, '<footer '));
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringContainsString('<strong>content</strong>', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertLessThan(strpos($html, $second['id']), strpos($html, $first['id']));
        $this->assertLessThan(strpos($html, $third['id']), strpos($html, $second['id']));
    }

    public function test_public_and_draft_preview_use_the_same_runtime_without_mutating_the_template(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        $this->settings(['site_name' => 'Preview site']);
        $element = $this->element('branding');
        $data = $this->data([$element]);
        $template = Template::query()->create([
            'title' => 'Composition', 'slug' => 'composition-footer', 'type' => 'site_footer',
            'status' => 'published', 'is_default' => true, 'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'site_footer', 'data' => $data]],
        ]);
        $this->settings(['footer_template_id' => $template->id]);
        $before = $template->getRawOriginal('blocks');
        $this->get(route('home'))->assertOk()->assertSee('data-footer-composition', false)->assertSee($element['id']);
        $template->update(['status' => 'draft']);
        $this->get(route('home'))->assertOk()->assertDontSee('data-footer-composition', false);
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.preview.templates.show', $template))->assertOk()->assertSee('noindex, nofollow')->assertSee($element['id']);
        $this->assertSame($before, $template->fresh()->getRawOriginal('blocks'));
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->get(route('admin.preview.templates.show', $template))->assertForbidden();
    }

    public function test_unknown_contracts_fail_closed_on_read_but_the_stored_payload_is_not_rewritten(): void
    {
        foreach ([99, 2] as $version) {
            $data = $this->data([$this->element('branding')]);
            $data['schema_version'] = $version;
            if ($version === 2) {
                $data['content']['composition']['main']['columns'][0]['elements'][0]['type'] = 'future_element';
            }
            $before = $data;
            $this->assertSame('', trim($this->render(app(SiteFooterRuntime::class)->prepare($data))));
            $this->assertSame($before, $data);
        }
    }

    public function test_design_settings_reach_the_same_renderer_without_reordering_or_inventing_columns(): void
    {
        $data = $this->data([$this->element('rich_text', ['source' => 'manual', 'text' => 'متن زنده فوتر'])]);
        $data['settings'] = ['container' => 'full', 'spacing' => 'compact', 'grid' => ['desktop' => 8, 'tablet' => 4, 'mobile' => 2]];
        $data['content']['composition']['main']['columns'][0]['settings'] = ['span' => 8];
        $prepared = app(SiteFooterRuntime::class)->prepare($data);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $html = $this->render($prepared);
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(1, substr_count($html, '<footer '));
        $this->assertStringContainsString('data-container="full" data-spacing="compact"', $html);
        $this->assertStringContainsString('--footer-grid-desktop: 8; --footer-grid-tablet: 4; --footer-grid-mobile: 2;', $html);
        $this->assertStringContainsString('--footer-column-span: 8;', $html);
        $this->assertSame($data['content']['composition']['main']['columns'][0]['id'], $prepared['composition']['main']['columns'][0]['id']);
        $data['settings']['grid']['desktop'] = 'auto';
        $this->assertStringContainsString('--footer-grid-desktop: min(8, var(--footer-auto-columns))', $this->render(app(SiteFooterRuntime::class)->prepare($data)));
    }

    private function data(array $elements, array $bottom = []): array
    {
        $data = $this->fixture('legacy-empty-v1');
        $data['schema_version'] = 2;
        $data['content']['composition'] = [
            'main' => ['columns' => [['id' => (string) Str::ulid(), 'elements' => $elements]]],
            'bottom' => ['elements' => $bottom],
        ];

        return $data;
    }

    private function element(string $type, array $content = [], bool $enabled = true): array
    {
        return ['id' => (string) Str::ulid(), 'type' => $type, 'enabled' => $enabled, 'content' => $content];
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/site-footer/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function settings(array $values): void
    {
        foreach ($values as $key => $value) {
            app(SettingsService::class)->set($key, (string) $value);
        }
    }

    private function media(string $filename): Media
    {
        return User::factory()->create()->addMedia(UploadedFile::fake()->image($filename, 200, 200))->toMediaCollection('media_library', 'public');
    }

    private function menuItem(Menu $menu, array $data): MenuItem
    {
        return MenuItem::query()->create(array_replace([
            'menu_id' => $menu->id, 'title' => 'Item', 'type' => MenuItem::TYPE_CUSTOM_URL,
            'url' => '/item', 'status' => 'active', 'target' => '_self', 'sort_order' => 3,
        ], $data));
    }

    private function render(array $footer): string
    {
        // Livewire's compiler may already be booted by an editor test in the same process.
        // Ignore only its morph bookkeeping; keep every byte of the actual Legacy markup.
        return str_replace(['<!--[if BLOCK]><![endif]-->', '<!--[if ENDBLOCK]><![endif]-->'], '',
            Blade::render(file_get_contents(resource_path('views/partials/blocks/site-footer-corporate-dark.blade.php')), ['footer' => $footer]));
    }
}
