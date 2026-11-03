<?php

namespace Tests\Unit;

use App\CMS\Blocks\BlockEditorSaveManager;
use App\CMS\Blocks\SiteFooter\SiteFooterDataNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SiteFooterDataNormalizerTest extends TestCase
{
    public function test_legacy_fixtures_keep_every_existing_content_field_and_identity(): void
    {
        foreach (['legacy-v1', 'legacy-empty-v1'] as $name) {
            $input = $this->fixture($name);
            $normalizer = app(SiteFooterDataNormalizer::class);
            $normalized = $normalizer->normalize($input);
            $legacyContent = $normalized['content'];
            unset($legacyContent['composition']);

            $this->assertSame($input['content'], $legacyContent);
            $this->assertSame($input['block_id'], $normalized['block_id']);
            $this->assertSame($input['template'], $normalized['template']);
            $this->assertSame(2, $normalized['schema_version']);
            $this->assertNull($normalized['content']['composition']);
            $this->assertSame($normalized, $normalizer->normalize($normalized));
        }
    }

    public function test_legacy_defaults_and_menu_and_badge_order_match_the_v1_contract(): void
    {
        $normalizer = app(SiteFooterDataNormalizer::class);
        $empty = $normalizer->normalize([]);
        $expected = $this->fixture('legacy-empty-v1')['content'];
        $content = $empty['content'];
        unset($content['composition']);
        $this->assertSame($expected, $content);

        $input = $this->fixture('legacy-v1');
        $input['content']['menu_columns'] = array_reverse($input['content']['menu_columns']);
        $input['content']['badges'] = array_reverse($input['content']['badges']);
        $input['content']['privacy_action'] = ['type' => 'custom_url', 'value' => 'javascript:alert(1)'];
        $normalized = $normalizer->normalize($input);

        $this->assertSame([103, 102, 101], array_column($normalized['content']['menu_columns'], 'menu_id'));
        $this->assertSame([201, 202], array_column($normalized['content']['badges'], 'media_id'));
        $this->assertFalse($normalized['content']['badges'][1]['enabled']);
        $this->assertNull($normalized['content']['privacy_action']);
    }

    public function test_legacy_normalization_is_idempotent_and_query_free(): void
    {
        DB::connection()->getPdo();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $normalizer = app(SiteFooterDataNormalizer::class);
        $input = $this->fixture('legacy-v1');
        $once = $normalizer->normalize($input);

        $this->assertSame($once, $normalizer->normalize($once));
        $this->assertSame([], DB::getQueryLog());
        $this->assertSame(1, $input['schema_version']);
    }

    public function test_the_complete_v2_contract_round_trips_without_queries_or_new_ids(): void
    {
        $input = $this->fixture('composition-v2');
        DB::connection()->getPdo();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $normalizer = app(SiteFooterDataNormalizer::class);

        $this->assertSame($input, $normalizer->normalize($input));
        $blocks = [['type' => 'site_footer', 'data' => $input]];
        foreach ([false, true] as $useHeroV2) {
            $saved = app(BlockEditorSaveManager::class)->prepare($blocks, $useHeroV2);
            $this->assertSame($blocks, $saved);
            $this->assertSame($saved, app(BlockEditorSaveManager::class)->prepare($saved, $useHeroV2));
        }
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_reordering_uuid_keyed_columns_and_elements_preserves_identity_and_order(): void
    {
        $input = $this->fixture('composition-v2');
        $columns = array_reverse($input['content']['composition']['main']['columns']);
        $columns[0]['elements'] = array_reverse($columns[0]['elements']);
        $ids = array_column($columns, 'id');
        $elementIds = array_column($columns[0]['elements'], 'id');
        $columns[0]['elements'] = array_combine(['uuid-c', 'uuid-a', 'uuid-b'], $columns[0]['elements']);
        $input['content']['composition']['main']['columns'] = array_combine(['uuid-3', 'uuid-1', 'uuid-2'], $columns);
        $normalized = app(SiteFooterDataNormalizer::class)->normalize($input);
        $actual = $normalized['content']['composition']['main']['columns'];

        $this->assertSame($ids, array_column($actual, 'id'));
        $this->assertSame($elementIds, array_column($actual[0]['elements'], 'id'));
        $this->assertSame([0, 1, 2], array_keys($actual));
        $this->assertSame($normalized, app(SiteFooterDataNormalizer::class)->normalize($normalized));
    }

    public function test_more_than_four_columns_and_an_intentionally_empty_composition_are_supported(): void
    {
        $input = $this->fixture('composition-v2');
        $input['content']['composition'] = ['main' => ['columns' => []], 'bottom' => ['elements' => []]];
        $normalizer = app(SiteFooterDataNormalizer::class);
        $empty = $normalizer->normalize($input);
        $this->assertSame($input, $empty);
        $this->assertNotNull($empty['content']['composition']);

        for ($index = 0; $index < 8; $index++) {
            $input['content']['composition']['main']['columns'][] = [
                'id' => '01K8Z0000000000000000001'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'elements' => [],
            ];
        }
        $result = $normalizer->normalize($input);
        $this->assertCount(8, $result['content']['composition']['main']['columns']);
        $this->assertSame($result, $normalizer->normalize($result));
    }

    public function test_missing_data_stays_unresolved_and_disabled_elements_are_retained(): void
    {
        $input = $this->fixture('composition-v2');
        $input['content']['composition']['main']['columns'][2]['elements'][0]['content']['media_id'] = null;
        $input['content']['composition']['main']['columns'][2]['elements'][0]['content']['directions_action'] = null;
        $normalized = app(SiteFooterDataNormalizer::class)->normalize($input);

        $this->assertNull($normalized['content']['composition']['main']['columns'][2]['elements'][0]['content']['media_id']);
        $this->assertNull($normalized['content']['composition']['main']['columns'][2]['elements'][0]['content']['directions_action']);
        $this->assertFalse($normalized['content']['composition']['main']['columns'][2]['elements'][2]['enabled']);
        $this->assertSame('site', $normalized['content']['composition']['main']['columns'][0]['elements'][0]['content']['source']);
        $this->assertNull($normalized['content']['composition']['main']['columns'][0]['elements'][0]['content']['name']);
    }

    public function test_nested_actions_contacts_and_design_settings_use_typed_values_without_resolving_references(): void
    {
        $input = $this->fixture('composition-v2');
        $input['settings']['grid'] = ['desktop' => '6', 'tablet' => '3', 'mobile' => '2'];
        $input['content']['composition']['main']['columns'][0]['elements'][2]['content'] = [
            'source' => 'manual',
            'items' => [['label' => ' تماس ', 'icon' => 'icon-call', 'action' => ['type' => 'phone', 'value' => '۰۲۱ ۱۲۳۴۵۶۷۸']]],
        ];
        $normalized = app(SiteFooterDataNormalizer::class)->normalize($input);
        $social = $normalized['content']['composition']['main']['columns'][0]['elements'][2]['content'];
        $this->assertSame('تماس', $social['items'][0]['label']);
        $this->assertSame('02112345678', $social['items'][0]['action']['value']);
        $this->assertSame(['desktop' => 6, 'tablet' => 3, 'mobile' => 2], $normalized['settings']['grid']);
        $this->assertSame(30, $normalized['content']['composition']['bottom']['elements'][1]['content']['items'][0]['action']['reference_id']);
        $this->assertSame($normalized, app(SiteFooterDataNormalizer::class)->normalize($normalized));
    }

    #[DataProvider('unsupportedInputs')]
    public function test_invalid_or_unknown_contracts_stop_the_shared_save_without_discarding_data(string $path, mixed $value, string $errorPath): void
    {
        $input = $this->fixture('composition-v2');
        Arr::set($input, $path, $value);
        $before = $input;

        try {
            app(BlockEditorSaveManager::class)->prepare([['type' => 'site_footer', 'data' => $input]], true);
            $this->fail('An unsupported footer contract was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($errorPath, $exception->errors());
        }
        $this->assertSame($before, $input);
    }

    public static function unsupportedInputs(): array
    {
        $root = 'content.composition';
        $element = $root.'.main.columns.0.elements.0';

        return [
            'future version' => ['schema_version', 99, 'schema_version'],
            'noninteger version' => ['schema_version', 2.5, 'schema_version'],
            'invalid version' => ['schema_version', 'future', 'schema_version'],
            'future renderer' => ['template', 'future-footer', 'template'],
            'v1 is not implicitly composed' => ['schema_version', 1, $root],
            'unknown element' => [$element.'.type', 'future_element', $element.'.type'],
            'raw html is not an element' => [$element.'.type', 'custom_html', $element.'.type'],
            'unknown disabled element' => [$root.'.main.columns.2.elements.2.type', 'future_form', $root.'.main.columns.2.elements.2.type'],
            'wrong region' => [$root.'.bottom.elements.0.type', 'branding', $root.'.bottom.elements.0.type'],
            'future field' => [$element.'.content.future', ['keep' => true], $element.'.content'],
            'invalid composition object' => [$root, 'invalid', $root],
            'invalid element list' => [$root.'.main.columns.0.elements', false, $root.'.main.columns.0.elements'],
            'invalid column id' => [$root.'.main.columns.0.id', '01JFOOTER00000000000000000', $root.'.main.columns.0.id'],
            'missing element id' => [$element.'.id', null, $element.'.id'],
            'duplicate id' => [$element.'.id', '01K8Z000000000000000000010', $element.'.id'],
            'cross-region duplicate id' => [$root.'.bottom.elements.0.id', '01K8Z000000000000000000011', $root.'.bottom.elements.0.id'],
            'fractional media id' => [$element.'.content.logo_media_id', '2.5', $element.'.content.logo_media_id'],
            'source outside contract' => [$element.'.content.source', 'automatic', $element.'.content.source'],
            'invalid boolean' => [$element.'.enabled', 'maybe', $element.'.enabled'],
            'unsupported map mode' => [$root.'.main.columns.2.elements.0.content.mode', 'iframe', $root.'.main.columns.2.elements.0.content.mode'],
            'unsafe action' => [$root.'.main.columns.2.elements.0.content.directions_action', ['type' => 'custom_url', 'value' => 'javascript:alert(1)'], $root.'.main.columns.2.elements.0.content.directions_action'],
            'future action' => [$root.'.main.columns.2.elements.0.content.directions_action.schema_version', 2, $root.'.main.columns.2.elements.0.content.directions_action.schema_version'],
            'invalid design' => ['settings.grid.desktop', 2.5, 'settings.grid.desktop'],
            'boolean is not a grid count' => ['settings.grid.tablet', true, 'settings.grid.tablet'],
            'boolean is not a span' => [$root.'.main.columns.0.settings.span', true, $root.'.main.columns.0.settings.span'],
            'fractional depth' => [$root.'.main.columns.1.elements.0.content.depth', 1.5, $root.'.main.columns.1.elements.0.content.depth'],
            'arbitrary css' => ['settings.css', 'body {display:none}', 'settings'],
            'invalid contact email' => [$root.'.main.columns.1.elements.1.content.overrides.email', 'invalid email', $root.'.main.columns.1.elements.1.content.overrides.email'],
        ];
    }

    public function test_composition_limits_reject_excess_payloads_instead_of_truncating(): void
    {
        foreach (['columns', 'elements', 'total'] as $limit) {
            $input = $this->fixture('composition-v2');
            $column = $input['content']['composition']['main']['columns'][0];
            if ($limit === 'columns') {
                $input['content']['composition']['main']['columns'] = array_fill(0, 9, $column);
            } elseif ($limit === 'elements') {
                $input['content']['composition']['main']['columns'][0]['elements'] = array_fill(0, 13, $column['elements'][0]);
            } else {
                $columns = [];
                for ($i = 0; $i < 5; $i++) {
                    $columns[] = ['id' => (string) Str::ulid(), 'elements' => array_map(
                        fn () => ['id' => (string) Str::ulid(), 'type' => 'image', 'content' => []],
                        range(1, 10),
                    )];
                }
                $input['content']['composition']['main']['columns'] = $columns;
            }
            try {
                app(SiteFooterDataNormalizer::class)->normalize($input);
                $this->fail('An over-limit composition was truncated or accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/site-footer/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
