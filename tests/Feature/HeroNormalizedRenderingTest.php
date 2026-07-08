<?php

namespace Tests\Feature;

use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\Models\User;
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
        $this->assertStringContainsString('hero-template-2--right', $html);
        $this->assertStringContainsString('value="/first"', $html);
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
