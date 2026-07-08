<?php

namespace Tests\Unit;

use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\CMS\Blocks\Hero\HeroMediaResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class HeroDataNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private HeroDataNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = app(HeroDataNormalizer::class);
    }

    public function test_default_legacy_text_and_incomplete_ctas_normalize_without_merging(): void
    {
        $result = $this->normalizer->normalize([
            'title' => 'Title', 'subtitle' => 'Lead', 'description' => 'Description',
            'primary_button_label' => 'Label only', 'secondary_button_url' => '/url-only',
        ]);

        $this->assertSame(2, $result['schema_version']);
        $this->assertNull($result['block_id']);
        $this->assertSame('default', $result['template']);
        $this->assertSame('Lead', $result['content']['lead']);
        $this->assertSame('Description', $result['content']['description']);
        $this->assertSame(['label' => 'Label only', 'url' => null], $result['content']['primary_cta']);
        $this->assertSame(['label' => null, 'url' => '/url-only'], $result['content']['secondary_cta']);
    }

    public function test_hero_one_maps_alias_height_content_and_dotted_effect(): void
    {
        $result = $this->normalizer->normalize([
            'template' => 'hero_1', 'hero_1_height' => 560, 'hero_1_mobile_height' => 420,
            'hero_1_title_second_line' => 'Second', 'eyebrow' => 'Eyebrow',
            'hero_1_theme' => 'animated_dotted_surface', 'animated_background_speed' => 'fast',
            'animated_background_color' => '#123456', 'hero_1_show_underline' => true,
        ]);

        $this->assertSame(['desktop' => 560, 'mobile' => 420], $result['settings']['height']);
        $this->assertSame('Second', $result['content']['title_secondary']);
        $this->assertSame('Eyebrow', $result['content']['eyebrow']['text']);
        $this->assertSame('dotted', $result['settings']['background_effect']['type']);
        $this->assertSame('fast', $result['settings']['background_effect']['speed']);
        $this->assertSame('#123456', $result['settings']['background_effect']['background_color_override']);
        $this->assertSame('underline', $result['settings']['title_decoration']);
    }

    public function test_explicit_hero_one_desktop_height_wins_over_alias(): void
    {
        $result = $this->normalizer->normalize([
            'template' => 'hero_1', 'hero_1_desktop_height' => 600, 'hero_1_height' => 500,
        ]);

        $this->assertSame(600, $result['settings']['height']['desktop']);
    }

    public function test_hero_two_image_and_video_shapes_are_preserved(): void
    {
        $image = $this->normalizer->normalize([
            'template' => 'hero_2', 'hero_2_background_type' => 'image', 'image' => '/image.jpg',
        ]);
        $video = $this->normalizer->normalize([
            'template' => 'hero_2', 'hero_2_background_type' => 'video', 'image' => '/fallback.jpg',
            'hero_2_video_url' => '/video.mp4', 'hero_2_video_poster' => '/poster.jpg',
        ]);

        $this->assertSame('image', $image['content']['media']['kind']);
        $this->assertSame('/image.jpg', $image['content']['media']['url']);
        $this->assertSame('video', $video['content']['media']['kind']);
        $this->assertSame('/fallback.jpg', $video['content']['media']['url']);
        $this->assertSame('/video.mp4', $video['content']['media']['video_url']);
        $this->assertSame('/poster.jpg', $video['content']['media']['poster_url']);
    }

    public function test_hero_three_stats_and_hidden_template_data_are_preserved(): void
    {
        $stats = [['value' => '10', 'label' => 'Years']];
        $selector = [['label' => 'A', 'url' => '/a']];
        $social = [['label' => 'Social', 'url' => '/social']];
        $result = $this->normalizer->normalize([
            'template' => 'hero_3', 'stats' => $stats, 'selector_items' => $selector,
            'selector_placeholder' => 'Choose', 'hero_1_social_links' => $social,
            'hero_1_scroll_label' => 'Scroll',
        ]);

        $this->assertSame($stats, $result['content']['stats']);
        $this->assertSame(['placeholder' => 'Choose', 'items' => $selector], $result['content']['selector']);
        $this->assertSame($social, $result['content']['social_links']);
        $this->assertSame('Scroll', $result['content']['scroll_label']);
    }

    public function test_responsive_values_keep_units_and_nulls_do_not_gain_fake_units(): void
    {
        $result = $this->normalizer->normalize([
            'image_width_value' => 80, 'image_width_unit' => '%',
            'image_height_value' => null, 'image_height_unit' => null,
        ]);

        $this->assertSame(['value' => 80, 'unit' => '%'], $result['settings']['media']['desktop']['width']);
        $this->assertSame(['value' => null, 'unit' => null], $result['settings']['media']['desktop']['height']);
        $this->assertSame(['value' => null, 'unit' => null], $result['settings']['media']['mobile']['width']);
    }

    public function test_paths_effect_maps_details_and_current_blade_defaults(): void
    {
        $result = $this->normalizer->normalize(['template' => 'hero_1', 'hero_1_theme' => 'animated_paths']);
        $effect = $result['settings']['background_effect'];

        $this->assertSame('paths', $effect['type']);
        $this->assertTrue($effect['enabled']);
        $this->assertSame('normal', $effect['speed']);
        $this->assertSame('medium', $effect['density']);
        $this->assertSame(0.35, $effect['opacity']);
        $this->assertSame(1, $effect['settings']['line_width']);
    }

    public function test_media_match_sets_source_id_and_unmatched_url_survives(): void
    {
        Storage::fake('public');
        $media = User::factory()->create()->addMedia(UploadedFile::fake()->image('hero.jpg'))
            ->toMediaCollection('media_library', 'public');

        $matched = $this->normalizer->normalize(['image' => $media->getUrl()]);
        $unmatched = $this->normalizer->normalize(['image' => 'https://example.test/manual.jpg']);

        $this->assertSame($media->id, $matched['content']['media']['source_id']);
        $this->assertSame($media->getUrl(), $matched['content']['media']['url']);
        $this->assertNull($unmatched['content']['media']['source_id']);
        $this->assertSame('https://example.test/manual.jpg', $unmatched['content']['media']['url']);
    }

    public function test_ambiguous_media_result_is_non_destructive(): void
    {
        $resolver = Mockery::mock(HeroMediaResolver::class);
        $resolver->shouldReceive('resolveSourceId')->andReturn(null);
        $normalizer = new HeroDataNormalizer($resolver);

        $result = $normalizer->normalize(['image' => '/ambiguous.jpg']);

        $this->assertNull($result['content']['media']['source_id']);
        $this->assertSame('/ambiguous.jpg', $result['content']['media']['url']);
    }

    public function test_normalization_is_idempotent_and_preserves_existing_v2_data(): void
    {
        $once = $this->normalizer->normalize(['template' => 'unknown-template', 'title' => 'Title']);
        $once['block_id'] = 'stable-id';
        $once['content']['media']['alt'] = 'Alt';
        $twice = $this->normalizer->normalize($once);

        $this->assertSame('unknown-template', $once['template']);
        $this->assertSame($once, $twice);
        $this->assertSame($twice, $this->normalizer->normalize($twice));
    }

    public function test_normalization_executes_no_write_queries(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower(ltrim($query->sql));
        });

        $this->normalizer->normalize(['image' => '/unmatched.jpg']);

        $writes = array_filter($queries, fn (string $sql): bool => preg_match('/^(insert|update|delete|replace|alter|create|drop|truncate)\b/', $sql) === 1);
        $this->assertSame([], array_values($writes));
    }
}
