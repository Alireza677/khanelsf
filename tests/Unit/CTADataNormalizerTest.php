<?php

namespace Tests\Unit;

use App\CMS\Blocks\CTA\CTADataNormalizer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CTADataNormalizerTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_legacy_cta_normalizes_to_v2_contract(): void
    {
        $result = $this->normalizer()->normalize([
            'block_id' => self::ID, 'cta_template' => 'image', 'eyebrow' => 'Eyebrow',
            'title' => 'Call us', 'description' => 'Description', 'heading_tag' => 'h1',
            'button_label' => 'Primary', 'button_url' => '/primary',
            'secondary_button_label' => 'Secondary', 'secondary_button_url' => '/secondary',
            'background_image' => '/background.jpg', 'content_width' => '640',
            'alignment' => 'center', 'section_background' => 'muted',
            'background_image_width_value' => '80', 'background_image_width_unit' => '%',
            'background_image_mobile_fit' => 'cover',
        ]);

        $this->assertSame(['block_id', 'schema_version', 'template', 'content', 'settings'], array_keys($result));
        $this->assertSame(self::ID, $result['block_id']);
        $this->assertSame(2, $result['schema_version']);
        $this->assertSame('image', $result['template']);
        $this->assertSame('Call us', $result['content']['title']);
        $this->assertSame([
            'label' => 'Primary',
            'action' => [
                'schema_version' => 1,
                'type' => 'custom_url',
                'value' => '/primary',
                'open_in_new_tab' => false,
            ],
        ], $result['content']['primary_cta']);
        $this->assertSame('/background.jpg', $result['content']['media']['url']);
        $this->assertSame(640, $result['settings']['content_width']);
        $this->assertSame(['value' => 80, 'unit' => '%'], $result['settings']['media']['desktop']['width']);
        $this->assertSame('cover', $result['settings']['media']['mobile']['fit']);
    }

    public function test_v2_cta_is_normalized_without_falling_back_to_legacy_keys(): void
    {
        $result = $this->normalizer()->normalize([
            'schema_version' => '2', 'template' => 'classic', 'title' => 'Legacy duplicate',
            'content' => ['title' => 'Canonical', 'primary_cta' => ['label' => 'Go', 'url' => '/go']],
            'settings' => ['heading_tag' => 'h2', 'alignment' => 'center', 'background' => 'default'],
        ]);

        $this->assertSame('Canonical', $result['content']['title']);
        $this->assertSame([
            'label' => 'Go',
            'action' => [
                'schema_version' => 1,
                'type' => 'custom_url',
                'value' => '/go',
                'open_in_new_tab' => false,
            ],
        ], $result['content']['primary_cta']);
        $this->assertSame('center', $result['settings']['alignment']);
        $this->assertArrayNotHasKey('title', $result);
    }

    public function test_future_contract_versions_use_the_nested_read_path(): void
    {
        $result = $this->normalizer()->normalize([
            'schema_version' => 3,
            'template' => 'image',
            'title' => 'Legacy duplicate',
            'content' => ['title' => 'Future canonical'],
        ]);

        $this->assertSame(2, $result['schema_version']);
        $this->assertSame('Future canonical', $result['content']['title']);
    }

    public function test_form_action_is_canonicalized_without_querying_the_form(): void
    {
        $result = $this->normalizer()->normalize([
            'schema_version' => 2,
            'content' => [
                'primary_cta' => [
                    'label' => 'Open form',
                    'url' => '/stale-url',
                    'action' => ['type' => 'form', 'form_id' => '12', 'display' => 'modal'],
                ],
            ],
        ]);

        $this->assertSame([
            'label' => 'Open form',
            'action' => [
                'schema_version' => 1,
                'type' => 'form',
                'reference_id' => 12,
                'display' => 'modal',
                'open_in_new_tab' => false,
            ],
        ], $result['content']['primary_cta']);
    }

    public function test_existing_form_action_without_display_keeps_a_null_legacy_fallback(): void
    {
        $result = $this->normalizer()->normalize([
            'schema_version' => 2,
            'content' => [
                'primary_cta' => [
                    'label' => 'Existing form',
                    'action' => ['type' => 'form', 'form_id' => 7],
                ],
            ],
        ]);

        $this->assertArrayNotHasKey('display', $result['content']['primary_cta']['action']);
    }

    public function test_normalization_is_idempotent_read_only_and_query_free(): void
    {
        $queries = [];
        DB::listen(fn ($query) => $queries[] = $query->sql);
        $once = $this->normalizer()->normalize(['title' => 'CTA', 'button_label' => 'Go', 'button_url' => '/go']);
        $twice = $this->normalizer()->normalize($once);

        $this->assertSame($once, $twice);
        $this->assertSame([], $queries);
    }

    private function normalizer(): CTADataNormalizer
    {
        return app(CTADataNormalizer::class);
    }
}
