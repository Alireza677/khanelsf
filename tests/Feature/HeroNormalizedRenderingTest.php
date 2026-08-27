<?php

namespace Tests\Feature;

use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\Models\Form;
use App\Models\User;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class HeroNormalizedRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_legacy_and_v2_render_with_semantic_parity(): void
    {
        $legacy = [
            'title' => 'Default title', 'subtitle' => 'Lead', 'description' => 'Description',
            'heading_tag' => 'h1', 'alignment' => 'center', 'section_background' => 'muted',
            'image' => 'https://example.test/default.jpg',
            'image_width_value' => 80, 'image_width_unit' => '%', 'image_fit' => 'contain',
            'primary_button_label' => 'Primary', 'primary_button_url' => '/primary',
        ];

        $html = $this->assertLegacyAndV2Parity($legacy);
        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('content-block--align-center', $html);
        $this->assertStringContainsString('content-block--muted', $html);
        $this->assertStringContainsString('--block-image-width: 80%', $html);
        $this->assertStringContainsString('href="/primary"', $html);
    }

    public function test_hero_three_legacy_and_v2_render_with_stats_icon_and_media_parity(): void
    {
        $legacy = [
            'template' => 'hero_3', 'hero_3_alignment' => 'left', 'eyebrow' => 'Eyebrow',
            'title' => 'Hero three', 'subtitle' => 'Lead', 'image' => 'https://example.test/three.jpg',
            'stats' => [['value' => '10', 'label' => 'Years', 'description' => 'Experience', 'icon' => 'icon-activity', 'icon_size' => 31]],
            'secondary_button_label' => 'Secondary', 'secondary_button_url' => '/secondary',
        ];

        $html = $this->assertLegacyAndV2Parity($legacy);
        $this->assertStringContainsString('hero-template-3--left', $html);
        $this->assertStringContainsString('hero-template-3__stat', $html);
        $this->assertStringContainsString('font-size: 31px', $html);
        $this->assertStringContainsString('Experience', $html);
    }

    public function test_hero_two_video_selector_and_height_have_legacy_v2_parity(): void
    {
        $legacy = [
            'template' => 'hero_2', 'title' => 'Hero two', 'hero_2_alignment' => 'right',
            'hero_2_height' => 540, 'hero_2_background_type' => 'video',
            'image' => 'https://example.test/fallback.jpg', 'hero_2_video_url' => 'https://example.test/video.mp4',
            'hero_2_video_poster' => 'https://example.test/poster.jpg', 'selector_placeholder' => 'Choose',
            'selector_items' => [['label' => 'First', 'url' => '/first']],
            'primary_button_label' => 'Continue',
            'secondary_button_label' => 'Help', 'secondary_button_url' => '/help',
        ];

        $html = $this->assertLegacyAndV2Parity($legacy);
        $this->assertStringContainsString('data-hero-template-2-video', $html);
        $this->assertStringContainsString('src="https://example.test/video.mp4"', $html);
        $this->assertStringContainsString('poster="https://example.test/poster.jpg"', $html);
        $this->assertStringContainsString('--hero-template-2-height: 540px', $html);
        $this->assertStringNotContainsString('hero-template-2--right', $html);
        $this->assertStringContainsString('data-hero-template-2-action="0"', $html);
        $this->assertStringContainsString('href="/first"', $html);
    }

    public function test_hero_two_ignores_legacy_alignment_during_rendering(): void
    {
        $base = ['template' => 'hero_2', 'title' => 'Direction-aware hero'];

        $this->assertSame(
            $this->render([...$base, 'hero_2_alignment' => 'left']),
            $this->render([...$base, 'hero_2_alignment' => 'right']),
        );
    }

    public function test_hero_two_selector_resolves_page_references_after_slug_changes_and_skips_invalid_items(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'first-page']);
        $hero = app(HeroDataNormalizer::class)->normalize([
            'schema_version' => 2,
            'template' => 'hero_2',
            'content' => [
                'title' => 'Canonical selector',
                'primary_cta' => ['label' => 'Continue'],
                'selector' => ['items' => [
                    ['label' => 'Internal', 'action' => ['type' => 'page', 'reference_id' => $page->id]],
                    ['label' => 'Custom', 'action' => ['type' => 'custom_url', 'value' => 'https://example.test/path']],
                    ['label' => 'Broken', 'action' => ['type' => 'page', 'reference_id' => 999999]],
                ]],
            ],
        ]);

        $page->update(['slug' => 'renamed-page']);
        $html = $this->render($hero);

        $this->assertStringContainsString('href="/renamed-page"', $html);
        $this->assertStringContainsString('href="https://example.test/path"', $html);
        $this->assertStringContainsString('>Broken</option>', $html);
        $this->assertStringNotContainsString('data-hero-template-2-action="2"', $html);
        $this->assertStringContainsString('Canonical selector', $html);
    }

    public function test_hero_two_primary_cta_never_uses_its_legacy_independent_action(): void
    {
        $hero = app(HeroDataNormalizer::class)->normalize([
            'schema_version' => 2,
            'template' => 'hero_2',
            'content' => [
                'title' => 'Selector authority',
                'primary_cta' => [
                    'label' => 'Continue',
                    'action' => ['type' => 'custom_url', 'value' => '/wrong-legacy-destination'],
                ],
                'selector' => ['items' => []],
            ],
        ]);

        $html = $this->render($hero);

        $this->assertStringNotContainsString('/wrong-legacy-destination', $html);
        $this->assertStringNotContainsString('href="#"', $html);
        $this->assertStringContainsString('type="button" disabled', $html);
        $this->assertStringContainsString('data-hero-template-2-button', $html);
    }

    public function test_hero_two_renders_one_disabled_trigger_and_canonical_action_for_each_valid_option(): void
    {
        $hero = app(HeroDataNormalizer::class)->normalize([
            'schema_version' => 2,
            'template' => 'hero_2',
            'content' => [
                'title' => 'Three choices',
                'primary_cta' => ['label' => 'Start'],
                'selector' => ['items' => [
                    ['label' => 'Option A', 'action' => ['type' => 'custom_url', 'value' => '/action-a']],
                    ['label' => 'Option B', 'action' => ['type' => 'custom_url', 'value' => '/action-b']],
                    ['label' => 'Option C', 'action' => ['type' => 'email', 'value' => 'hello@example.test']],
                ]],
            ],
        ]);

        $html = $this->render($hero);

        $this->assertSame(3, substr_count($html, 'data-hero-template-2-action='));
        $this->assertStringContainsString('data-hero-template-2-action-slot', $html);
        $this->assertStringContainsString('href="/action-a"', $html);
        $this->assertStringContainsString('href="/action-b"', $html);
        $this->assertStringContainsString('href="mailto:hello@example.test"', $html);
    }

    public function test_hero_two_selected_options_preserve_form_page_and_modal_presentations(): void
    {
        $pageForm = Form::query()->create([
            'name' => 'Hero page form', 'slug' => 'hero-page-form', 'status' => 'published', 'display_mode' => 'page',
        ]);
        $modalForm = Form::query()->create([
            'name' => 'Hero modal form', 'slug' => 'hero-modal-form', 'status' => 'published', 'display_mode' => 'modal',
        ]);
        $hero = app(HeroDataNormalizer::class)->normalize([
            'schema_version' => 2,
            'template' => 'hero_2',
            'block_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            'content' => [
                'title' => 'Form choices',
                'primary_cta' => ['label' => 'Continue'],
                'selector' => ['items' => [
                    ['label' => 'Page form', 'action' => ['type' => 'form', 'reference_id' => $pageForm->id, 'display' => 'page']],
                    ['label' => 'Modal form', 'action' => ['type' => 'form', 'reference_id' => $modalForm->id, 'display' => 'modal']],
                ]],
            ],
        ]);

        $html = view('partials.blocks.hero', [
            'data' => $hero,
            'context' => ['page_url' => '/hero-source'],
        ])->render();

        $this->assertStringContainsString(route('forms.context', $pageForm->slug), $html);
        $this->assertStringContainsString('data-form-action-modal-url="'.route('forms.modal', $modalForm->slug).'"', $html);
        $this->assertStringContainsString('name="_context_page_url" value="/hero-source"', $html);
        $this->assertSame(2, substr_count($html, '<form'));
    }

    public function test_hero_two_valid_default_option_is_selected_for_initial_sync(): void
    {
        $hero = app(HeroDataNormalizer::class)->normalize([
            'schema_version' => 2,
            'template' => 'hero_2',
            'content' => [
                'title' => 'Default choice',
                'primary_cta' => ['label' => 'Continue'],
                'selector' => [
                    'default_index' => 1,
                    'items' => [
                        ['label' => 'First', 'action' => ['type' => 'custom_url', 'value' => '/first']],
                        ['label' => 'Second', 'action' => ['type' => 'custom_url', 'value' => '/second', 'open_in_new_tab' => true]],
                    ],
                ],
            ],
        ]);

        $html = $this->render($hero);

        $this->assertMatchesRegularExpression('/<option value="1" selected(?:="selected")?>Second<\/option>/', $html);
        $this->assertStringContainsString('href="/second"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
    }

    public function test_hero_two_keeps_invalid_option_selectable_but_emits_no_action_template(): void
    {
        $hero = app(HeroDataNormalizer::class)->normalize([
            'schema_version' => 2,
            'template' => 'hero_2',
            'content' => [
                'title' => 'Invalid choice',
                'primary_cta' => ['label' => 'Continue'],
                'selector' => ['items' => [
                    ['label' => 'Valid', 'action' => ['type' => 'custom_url', 'value' => '/valid']],
                    ['label' => 'Invalid', 'action' => ['type' => 'page', 'reference_id' => 999999]],
                ]],
            ],
        ]);

        $html = $this->render($hero);

        $this->assertMatchesRegularExpression('/<option value="1"[^>]*>Invalid<\/option>/', $html);
        $this->assertStringNotContainsString('data-hero-template-2-action="1"', $html);
    }

    public function test_hero_two_javascript_maps_string_action_keys_via_the_exact_data_attribute(): void
    {
        foreach ([
            resource_path('js/app.js'),
            resource_path('views/layouts/app.blade.php'),
        ] as $path) {
            $javascript = file_get_contents($path);

            $this->assertStringContainsString(
                "template.getAttribute('data-hero-template-2-action')",
                $javascript,
            );
            $this->assertStringNotContainsString('template.dataset.heroTemplate2Action', $javascript);
            $this->assertStringContainsString('actions.get(select.value)', $javascript);
            $this->assertStringContainsString('actionSlot.replaceChildren()', $javascript);
        }
    }

    public function test_all_hero_one_treatments_have_legacy_v2_parity(): void
    {
        foreach ([
            'image' => ['image' => 'https://example.test/one.jpg', 'overlay_opacity' => 40, 'hero_1_height' => 560, 'hero_1_mobile_height' => 420],
            'animated_dotted_surface' => ['animated_background_color' => '#123456', 'animated_dots_color' => '#abcdef', 'animated_background_speed' => 'fast'],
            'animated_paths' => ['paths_background_color' => '#112233', 'paths_color' => '#fedcba', 'paths_speed' => 'slow', 'paths_line_width' => 1.4],
        ] as $theme => $specific) {
            $legacy = [
                'template' => 'hero_1', 'hero_1_theme' => $theme, 'eyebrow' => 'Eyebrow',
                'hero_1_eyebrow_icon' => 'icon-activity', 'hero_1_eyebrow_icon_size' => 28,
                'title' => 'Hero one', 'hero_1_title_second_line' => 'Second', 'subtitle' => 'Lead',
                'hero_1_show_underline' => true,
                'primary_button_label' => 'Primary', 'primary_button_url' => '/primary',
                'hero_1_social_links' => [['label' => 'Social', 'url' => '/social', 'icon' => 'icon-activity', 'icon_size' => 18]],
                'hero_1_scroll_label' => 'Scroll',
                ...$specific,
            ];

            $html = $this->assertLegacyAndV2Parity($legacy);
            $this->assertStringContainsString('Second', $html);
            $this->assertStringContainsString('font-size: 28px', $html);
            $this->assertStringContainsString('hero-template-1__underline', $html);

            if ($theme === 'animated_dotted_surface') {
                $this->assertStringContainsString('data-hero-dotted-surface', $html);
                $this->assertStringContainsString('--hero-animated-background-color: #123456', $html);
            }

            if ($theme === 'animated_paths') {
                $this->assertStringContainsString('data-hero-animated-paths', $html);
                $this->assertStringContainsString('--hero-paths-background: #112233', $html);
            }
        }
    }

    public function test_dispatcher_preserves_unknown_key_but_renders_default_view(): void
    {
        $legacy = ['template' => 'unknown-template', 'title' => 'Fallback title'];
        $normalized = app(HeroDataNormalizer::class)->normalize($legacy);

        $this->assertSame('unknown-template', $normalized['template']);
        $this->assertStringContainsString('block-hero', $this->render($legacy));
    }

    public function test_frontend_normalization_is_independent_from_editor_rollout_flag(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $legacy = ['template' => 'hero_1', 'title' => 'Rollback legacy', 'hero_1_theme' => 'image'];
        $v2 = app(HeroDataNormalizer::class)->normalize($legacy);

        $this->assertStringContainsString('Rollback legacy', $this->render($legacy));
        $this->assertSame($this->render($legacy), $this->render($v2));
        $this->assertSame(2, $v2['schema_version']);
    }

    public function test_media_resolver_uses_one_lookup_for_repeated_urls_in_request_scope(): void
    {
        $user = User::factory()->create();
        $media = Media::query()->create([
            'model_type' => $user::class, 'model_id' => $user->id, 'uuid' => fake()->uuid(),
            'collection_name' => 'media_library', 'name' => 'hero', 'file_name' => 'hero.jpg',
            'mime_type' => 'image/jpeg', 'disk' => 'public', 'conversions_disk' => 'public', 'size' => 100,
            'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [], 'responsive_images' => [],
        ]);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'from "media"') || str_contains(strtolower($query->sql), 'from `media`')) {
                $queries[] = $query->sql;
            }
        });

        $url = $media->getUrl();
        $this->render(['title' => 'One', 'image' => $url]);
        $this->render(['title' => 'Two', 'image' => $url]);

        $this->assertCount(1, $queries);
    }

    public function test_hero_template_blades_have_no_direct_legacy_data_lookup(): void
    {
        foreach (['default', 'hero_1', 'hero_2', 'hero_3'] as $template) {
            $source = file_get_contents(resource_path("views/partials/blocks/hero/{$template}.blade.php"));
            $this->assertStringNotContainsString('$data[', $source, "{$template} still reads legacy data.");
        }
    }

    public function test_hero_one_feature_toggles_and_mode_are_runtime_authorities(): void
    {
        $hero = app(HeroDataNormalizer::class)->normalize([
            'template' => 'hero_1', 'title' => 'Visible title', 'eyebrow' => 'Hidden eyebrow',
            'hero_1_title_second_line' => 'Hidden second line', 'hero_1_show_underline' => true,
            'primary_button_label' => 'Hidden CTA', 'primary_button_url' => '/hidden',
            'hero_1_theme' => 'animated_dotted_surface',
        ]);
        $hero['content']['eyebrow']['enabled'] = false;
        $hero['content']['title_secondary_enabled'] = false;
        $hero['content']['ctas_enabled'] = false;
        $hero['settings']['background_treatment'] = 'image';

        $html = $this->render($hero);

        $this->assertStringNotContainsString('Hidden eyebrow', $html);
        $this->assertStringNotContainsString('Hidden second line', $html);
        $this->assertStringNotContainsString('Hidden CTA', $html);
        $this->assertStringNotContainsString('data-hero-dotted-surface', $html);
        $this->assertStringNotContainsString('data-hero-animated-paths', $html);
    }

    private function assertLegacyAndV2Parity(array $legacy): string
    {
        $legacyHtml = $this->render($legacy);
        $v2Html = $this->render(app(HeroDataNormalizer::class)->normalize($legacy));

        $this->assertSame($legacyHtml, $v2Html);

        return $legacyHtml;
    }

    private function render(array $data): string
    {
        return view('partials.blocks.hero', ['data' => $data])->render();
    }
}
