<?php

namespace Tests\Unit;

use App\CMS\Blocks\FeatureGrid\FeatureGridDataNormalizer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FeatureGridDataNormalizerTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_legacy_static_items_become_ordered_canonical_actions(): void
    {
        $result = $this->normalizer()->normalize([
            'block_id' => self::ID,
            'section_title' => 'Features',
            'section_description' => 'Description',
            'eyebrow' => 'Eyebrow',
            'heading_tag' => 'h1',
            'section_background' => 'muted',
            'alignment' => 'left',
            'items' => [
                [
                    'title' => 'First',
                    'description' => 'First description',
                    'icon' => 'home',
                    'icon_size' => 28,
                    'image' => '/first.jpg',
                    'image_width_value' => 80,
                    'image_width_unit' => '%',
                    'button_label' => 'First action',
                    'button_url' => '/first',
                ],
                [
                    'title' => 'Second',
                    'button_label' => 'Second action',
                    'action' => ['type' => 'email', 'value' => 'info@example.com'],
                ],
                [],
            ],
        ]);

        $this->assertSame(
            ['block_id', 'schema_version', 'template', 'content', 'settings'],
            array_keys($result),
        );
        $this->assertSame(self::ID, $result['block_id']);
        $this->assertSame(1, $result['schema_version']);
        $this->assertSame(['First', 'Second'], array_column($result['content']['items'], 'title'));
        $this->assertSame('/first', data_get($result, 'content.items.0.action.value'));
        $this->assertSame('email', data_get($result, 'content.items.1.action.type'));
        $this->assertArrayNotHasKey('button_url', $result['content']['items'][0]);
        $this->assertSame('Eyebrow', $result['settings']['eyebrow']);
        $this->assertSame('h1', $result['settings']['heading_tag']);
        $this->assertSame('muted', $result['settings']['section_background']);
        $this->assertSame(80, $result['content']['items'][0]['image_width_value']);
    }

    public function test_multiple_sanitized_existing_grids_and_dynamic_shape_are_supported(): void
    {
        $fixtures = [
            [
                'section_title' => 'Grid One',
                'items' => [
                    ['title' => 'One', 'button_label' => 'One', 'button_url' => '/one'],
                    ['title' => 'Two', 'button_label' => 'Two', 'button_url' => '/two'],
                ],
            ],
            [
                'section_title' => 'Grid Two',
                'items' => [
                    ['title' => 'Three', 'button_label' => 'Three', 'button_url' => '/three'],
                    ['title' => 'Four', 'button_label' => 'Four', 'button_url' => '/four'],
                ],
            ],
            [
                'section_title' => 'Grid Three',
                'items' => [
                    ['title' => 'Five', 'button_label' => 'Five', 'button_url' => '/five'],
                    ['title' => 'Six', 'button_label' => 'Six', 'button_url' => '/six'],
                ],
            ],
            [
                'section_title' => 'Dynamic',
                'items_mode' => 'dynamic',
                'dynamic_source' => 'projects',
                'dynamic_rows' => 2,
                'dynamic_columns' => 4,
                'dynamic_button_label' => 'View',
                'dynamic_button_overrides' => [
                    ['record_id' => '12', 'button_label' => 'Exact project'],
                ],
            ],
        ];

        $normalized = array_map(
            fn (array $fixture): array => $this->normalizer()->normalize($fixture),
            $fixtures,
        );

        $this->assertSame(6, collect($normalized)->sum(
            fn (array $grid): int => count($grid['content']['items']),
        ));
        $this->assertSame('dynamic', $normalized[3]['content']['items_mode']);
        $this->assertSame('projects', $normalized[3]['content']['dynamic_source']);
        $this->assertSame(2, $normalized[3]['settings']['dynamic_rows']);
        $this->assertSame(4, $normalized[3]['settings']['dynamic_columns']);
        $this->assertSame(12, $normalized[3]['content']['dynamic_button_overrides'][0]['record_id']);
    }

    public function test_normalization_is_idempotent_query_free_and_nullable(): void
    {
        $queries = [];
        DB::listen(fn ($query) => $queries[] = $query->sql);
        $once = $this->normalizer()->normalize([
            'section_title' => 'Grid',
            'items' => [
                ['title' => 'No action', 'button_label' => 'Label only'],
                ['title' => 'Unsafe', 'button_url' => 'javascript:alert(1)'],
            ],
        ]);
        $twice = $this->normalizer()->normalize($once);

        $this->assertSame($once, $twice);
        $this->assertNull($once['content']['items'][0]['action']);
        $this->assertNull($once['content']['items'][1]['action']);
        $this->assertSame([], $queries);
    }

    private function normalizer(): FeatureGridDataNormalizer
    {
        return app(FeatureGridDataNormalizer::class);
    }
}
