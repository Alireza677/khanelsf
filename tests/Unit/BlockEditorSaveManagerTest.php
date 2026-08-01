<?php

namespace Tests\Unit;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockEditorSaveManager;
use App\CMS\Blocks\BlockIdentityManager;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\CMS\Blocks\Hero\HeroMediaResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockEditorSaveManagerTest extends TestCase
{
    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const SECOND_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    public function test_legacy_mode_changes_identity_only(): void
    {
        $blocks = [['type' => 'hero', 'data' => ['block_id' => self::ID, 'title' => 'Legacy']]];

        $this->assertSame($blocks, $this->manager()->prepare($blocks, false));
    }

    public function test_v2_mode_enforces_canonical_root_and_preserves_non_hero_payload(): void
    {
        $blocks = [
            ['type' => 'hero', 'data' => [
                'block_id' => self::ID, 'schema_version' => 99, 'template' => 'custom_future_template',
                'title' => 'legacy duplicate', 'content' => [
                    'title' => 'Canonical', 'stats' => [['value' => '1', 'label' => 'One']],
                    'selector' => ['placeholder' => null, 'items' => [['label' => 'A', 'url' => '/a']]],
                    'social_links' => [['label' => 'Social', 'url' => '/social']],
                    'media' => ['url' => '/manual.jpg', 'source_id' => 999, 'poster_url' => '/poster.jpg', 'poster_source_id' => 998],
                    'primary_cta' => ['label' => 'Only label', 'url' => null],
                ],
                'settings' => ['background_effect' => ['type' => 'paths', 'settings' => ['line_width' => 2]]],
            ]],
            ['type' => 'unknown', 'data' => ['block_id' => self::SECOND_ID, 'custom' => ['keep' => true]]],
        ];
        $result = $this->manager(['/poster.jpg' => 42])->prepare($blocks, true);
        $hero = $result[0]['data'];

        $this->assertSame(['block_id', 'schema_version', 'template', 'content', 'settings'], array_keys($hero));
        $this->assertSame(2, $hero['schema_version']);
        $this->assertSame('custom_future_template', $hero['template']);
        $this->assertSame('Canonical', $hero['content']['title']);
        $this->assertNull($hero['content']['media']['source_id']);
        $this->assertSame(42, $hero['content']['media']['poster_source_id']);
        $this->assertSame(['label' => 'Only label', 'url' => null], $hero['content']['primary_cta']);
        $this->assertSame(2, $hero['settings']['background_effect']['settings']['line_width']);
        $this->assertArrayNotHasKey('title', $hero);
        $this->assertSame($blocks[1], $result[1]);
    }

    public function test_legacy_hero_mode_still_canonicalizes_cta_at_the_save_boundary(): void
    {
        $result = $this->manager()->prepare([[
            'type' => 'cta',
            'data' => [
                'block_id' => self::ID,
                'cta_template' => 'classic',
                'title' => 'Legacy CTA',
                'button_label' => 'Continue',
                'button_url' => '/continue',
            ],
        ]], false);

        $cta = $result[0]['data'];

        $this->assertSame(['block_id', 'schema_version', 'template', 'content', 'settings'], array_keys($cta));
        $this->assertSame(self::ID, $cta['block_id']);
        $this->assertSame(2, $cta['schema_version']);
        $this->assertSame('Legacy CTA', $cta['content']['title']);
        $this->assertSame([
            'label' => 'Continue',
            'action' => [
                'schema_version' => 1,
                'type' => 'custom_url',
                'value' => '/continue',
                'open_in_new_tab' => false,
            ],
        ], $cta['content']['primary_cta']);
        $this->assertArrayNotHasKey('button_label', $cta);
    }

    public function test_v2_save_is_idempotent_linear_and_uses_no_query_per_block(): void
    {
        $manager = $this->manager();
        $blocks = array_map(fn (int $index): array => [
            'type' => 'hero',
            'data' => ['schema_version' => 2, 'template' => 'hero_1', 'content' => ['title' => "Hero {$index}"]],
        ], range(1, 1000));
        $queries = [];
        DB::listen(fn ($query) => $queries[] = $query->sql);

        $once = $manager->prepare($blocks, true);
        $twice = $manager->prepare($once, true);

        $this->assertSame($once, $twice);
        $this->assertCount(1000, array_unique(array_column(array_column($once, 'data'), 'block_id')));
        $this->assertSame([], $queries);
    }

    private function manager(array $resolved = []): BlockEditorSaveManager
    {
        $resolver = new class($resolved) extends HeroMediaResolver
        {
            public function __construct(private readonly array $resolved) {}

            public function resolveSourceId(?string $url): ?int
            {
                return $this->resolved[$url ?? ''] ?? null;
            }
        };
        $identities = new BlockIdentityManager;
        $hydrator = new BlockEditorHydrator(
            $identities,
            new HeroDataNormalizer($resolver),
            app(CTADataNormalizer::class),
        );

        return new BlockEditorSaveManager($hydrator, $identities, $resolver);
    }
}
