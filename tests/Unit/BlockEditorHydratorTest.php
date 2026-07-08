<?php

namespace Tests\Unit;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockIdentityManager;
use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\CMS\Blocks\Hero\HeroMediaResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockEditorHydratorTest extends TestCase
{
    private const FIRST = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const SECOND = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    public function test_empty_and_legacy_editor_mode_preserve_flat_shape_and_add_identity(): void
    {
        $hydrator = $this->hydrator([self::FIRST]);

        $this->assertSame([], $hydrator->hydrate([]));
        $result = $hydrator->hydrate([['type' => 'hero', 'data' => ['title' => 'Legacy']]]);

        $this->assertSame('Legacy', $result[0]['data']['title']);
        $this->assertSame(self::FIRST, $result[0]['data']['block_id']);
        $this->assertArrayNotHasKey('content', $result[0]['data']);
        $this->assertArrayNotHasKey('settings', $result[0]['data']);
    }

    public function test_active_mode_preserves_order_unknown_data_and_repairs_duplicate_ids(): void
    {
        $hydrator = $this->hydrator([self::SECOND]);
        $blocks = [
            ['type' => 'unknown_custom_block', 'data' => ['block_id' => self::FIRST, 'nested' => ['keep' => true]]],
            ['type' => 'hero', 'data' => ['block_id' => self::FIRST, 'title' => 'Second']],
        ];
        $result = $hydrator->hydrate($blocks);

        $this->assertSame(['unknown_custom_block', 'hero'], array_column($result, 'type'));
        $this->assertSame(['keep' => true], $result[0]['data']['nested']);
        $this->assertSame(self::FIRST, $result[0]['data']['block_id']);
        $this->assertSame(self::SECOND, $result[1]['data']['block_id']);
        $this->assertSame($result, $hydrator->hydrate($result));
    }

    public function test_future_v2_mode_normalizes_only_hero_and_preserves_hidden_content(): void
    {
        $hydrator = $this->hydrator([self::FIRST, self::SECOND]);
        $stats = [['value' => '1', 'label' => 'One']];
        $social = [['label' => 'Social', 'url' => '/social']];
        $blocks = [
            ['type' => 'hero', 'data' => [
                'template' => 'hero_1', 'title' => 'Legacy', 'selector_items' => [['label' => 'A', 'url' => '/a']],
                'stats' => $stats, 'hero_1_social_links' => $social, 'hero_1_scroll_label' => 'Scroll',
                'hero_1_title_second_line' => 'Second', 'hero_2_video_url' => '/video.mp4',
            ]],
            ['type' => 'faq', 'data' => ['section_title' => 'FAQ']],
        ];
        $result = $hydrator->hydrateV2($blocks);

        $hero = $result[0]['data'];
        $this->assertSame(2, $hero['schema_version']);
        $this->assertSame('Legacy', $hero['content']['title']);
        $this->assertSame('Second', $hero['content']['title_secondary']);
        $this->assertSame($stats, $hero['content']['stats']);
        $this->assertSame($social, $hero['content']['social_links']);
        $this->assertSame('/video.mp4', $hero['content']['media']['video_url']);
        $this->assertArrayNotHasKey('title', $hero);
        $this->assertSame('FAQ', $result[1]['data']['section_title']);
    }

    public function test_v2_input_is_idempotent_and_hydration_has_no_writes(): void
    {
        $hydrator = $this->hydrator([self::FIRST]);
        $v2 = app(HeroDataNormalizer::class)->normalize(['title' => 'Title']);
        $queries = [];
        DB::listen(fn ($query) => $queries[] = strtolower(ltrim($query->sql)));

        $first = $hydrator->hydrateV2([['type' => 'hero', 'data' => $v2]]);
        $second = $hydrator->hydrateV2($first);

        $this->assertSame($first, $second);
        $writes = array_filter($queries, fn (string $sql): bool => preg_match('/^(insert|update|delete|replace|alter|create|drop|truncate)\b/', $sql) === 1);
        $this->assertSame([], array_values($writes));
    }

    public function test_multiple_heroes_and_large_lists_remain_unique(): void
    {
        $ids = array_map(fn (int $index): string => sprintf('01ARZ3NDEKTSV4RRFFQ69G%04d', $index), range(0, 199));
        $hydrator = $this->hydrator($ids);
        $blocks = array_map(fn (int $index): array => ['type' => $index % 2 ? 'hero' : 'faq', 'data' => ['position' => $index]], range(0, 199));
        $result = $hydrator->hydrate($blocks);

        $this->assertSame(range(0, 199), array_column(array_column($result, 'data'), 'position'));
        $this->assertCount(200, array_unique(array_column(array_column($result, 'data'), 'block_id')));
    }

    private function hydrator(array $ids): BlockEditorHydrator
    {
        $index = 0;
        $identities = new BlockIdentityManager(function () use (&$index, $ids): string {
            return $ids[$index++] ?? throw new \RuntimeException('Test ID sequence exhausted.');
        });
        $resolver = new class extends HeroMediaResolver
        {
            public function resolveSourceId(?string $url): ?int
            {
                return null;
            }
        };

        return new BlockEditorHydrator($identities, new HeroDataNormalizer($resolver));
    }
}
