<?php

namespace Tests\Unit;

use App\CMS\Blocks\BlockIdentityManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BlockIdentityManagerTest extends TestCase
{
    private const FIRST = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    private const SECOND = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    private const THIRD = '01ARZ3NDEKTSV4RRFFQ69G5FAX';

    public function test_missing_ids_are_added_and_existing_unique_id_is_preserved(): void
    {
        $manager = $this->manager([self::SECOND]);
        $result = $manager->ensureUniqueBlockIds([
            ['type' => 'hero', 'data' => ['block_id' => self::FIRST, 'title' => 'A']],
            ['type' => 'cta', 'data' => ['title' => 'B']],
        ]);

        $this->assertSame(self::FIRST, $result[0]['data']['block_id']);
        $this->assertSame(self::SECOND, $result[1]['data']['block_id']);
    }

    public function test_duplicate_ids_keep_first_and_rekey_later_occurrences(): void
    {
        $manager = $this->manager([self::SECOND, self::THIRD]);
        $result = $manager->ensureUniqueBlockIds([
            ['type' => 'hero', 'data' => ['block_id' => self::FIRST]],
            ['type' => 'hero', 'data' => ['block_id' => self::FIRST]],
            ['type' => 'hero', 'data' => ['block_id' => self::FIRST]],
        ]);

        $this->assertSame([self::FIRST, self::SECOND, self::THIRD], array_column(array_column($result, 'data'), 'block_id'));
        $this->assertSame($result, $manager->ensureUniqueBlockIds($result));
    }

    public function test_edit_and_reorder_preserve_identity(): void
    {
        $manager = $this->manager([]);
        $blocks = [
            ['type' => 'hero', 'data' => ['block_id' => self::FIRST, 'title' => 'Edited']],
            ['type' => 'cta', 'data' => ['block_id' => self::SECOND]],
        ];

        $edited = $manager->ensureUniqueBlockIds($blocks);
        $reordered = $manager->ensureUniqueBlockIds(array_reverse($edited));

        $this->assertSame(self::FIRST, $edited[0]['data']['block_id']);
        $this->assertSame(self::SECOND, $reordered[0]['data']['block_id']);
        $this->assertSame(self::FIRST, $reordered[1]['data']['block_id']);
    }

    public function test_clone_and_document_copy_receive_fresh_ids(): void
    {
        $manager = $this->manager([self::SECOND, self::THIRD]);
        $block = ['type' => 'hero', 'data' => ['block_id' => self::FIRST, 'title' => 'A']];

        $clone = $manager->prepareClonedBlock($block);
        $copy = $manager->regenerateDocumentIds([$block]);

        $this->assertSame(self::FIRST, $block['data']['block_id']);
        $this->assertSame(self::SECOND, $clone['data']['block_id']);
        $this->assertSame(self::THIRD, $copy[0]['data']['block_id']);
    }

    public function test_identity_validation_is_case_insensitive_but_preserves_stored_case(): void
    {
        $lowercase = strtolower(self::FIRST);
        $manager = $this->manager([self::SECOND, self::THIRD, '01ARZ3NDEKTSV4RRFFQ69G5FAY']);
        $result = $manager->ensureUniqueBlockIds([
            ['type' => 'hero', 'data' => ['block_id' => $lowercase]],
            ['type' => 'cta', 'data' => ['block_id' => self::FIRST]],
            ['type' => 'faq', 'data' => ['block_id' => '550e8400-e29b-41d4-a716-446655440000']],
            ['type' => 'gallery', 'data' => ['block_id' => 'arbitrary']],
        ]);

        $this->assertSame($lowercase, $result[0]['data']['block_id']);
        $this->assertSame(self::SECOND, $result[1]['data']['block_id']);
        $this->assertSame(self::THIRD, $result[2]['data']['block_id']);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $result[3]['data']['block_id']);
    }

    public function test_document_regeneration_preserves_payload_and_rekeys_every_valid_block(): void
    {
        $manager = $this->manager([self::SECOND, self::THIRD]);
        $original = [
            ['type' => 'hero', 'data' => ['block_id' => self::FIRST, 'title' => 'A']],
            ['type' => 'unknown', 'data' => ['block_id' => self::FIRST, 'nested' => ['keep' => true]]],
            'malformed',
        ];

        $copy = $manager->regenerateDocumentIds($original);

        $this->assertSame([self::SECOND, self::THIRD], array_column(array_column(array_slice($copy, 0, 2), 'data'), 'block_id'));
        $this->assertSame('A', $copy[0]['data']['title']);
        $this->assertSame(['keep' => true], $copy[1]['data']['nested']);
        $this->assertSame('malformed', $copy[2]);
        $this->assertSame(self::FIRST, $original[0]['data']['block_id']);
    }

    public function test_unknown_block_data_is_preserved_and_null_data_is_handled(): void
    {
        $manager = $this->manager([self::FIRST, self::SECOND]);
        $result = $manager->ensureUniqueBlockIds([
            ['type' => 'unknown_custom_block', 'data' => ['custom' => ['nested' => true]]],
            ['type' => 'broken', 'data' => null, 'other' => 'preserved'],
            'malformed-entry',
            ['data' => ['custom' => 'malformed-array']],
        ]);

        $this->assertSame(['nested' => true], $result[0]['data']['custom']);
        $this->assertSame(self::FIRST, $result[0]['data']['block_id']);
        $this->assertSame(self::SECOND, $result[1]['data']['block_id']);
        $this->assertSame('preserved', $result[1]['other']);
        $this->assertSame('malformed-entry', $result[2]);
        $this->assertSame(['data' => ['custom' => 'malformed-array']], $result[3]);
    }

    public function test_empty_and_large_lists_are_linear_and_normalization_has_no_writes(): void
    {
        $ids = array_map(fn (int $index): string => sprintf('01ARZ3NDEKTSV4RRFFQ69G%04d', $index), range(0, 999));
        $manager = $this->manager($ids);
        $blocks = array_fill(0, 1000, ['type' => 'hero', 'data' => []]);
        $queries = [];
        DB::listen(fn ($query) => $queries[] = strtolower(ltrim($query->sql)));

        $this->assertSame([], $manager->ensureUniqueBlockIds([]));
        $result = $manager->ensureUniqueBlockIds($blocks);

        $this->assertCount(1000, $result);
        $this->assertCount(1000, array_unique(array_column(array_column($result, 'data'), 'block_id')));
        $this->assertSame([], $queries);
    }

    private function manager(array $ids): BlockIdentityManager
    {
        $index = 0;

        return new BlockIdentityManager(function () use (&$index, $ids): string {
            return $ids[$index++] ?? throw new \RuntimeException('Test ID sequence exhausted.');
        });
    }
}
