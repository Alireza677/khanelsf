<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use App\Services\EditorHistory\EditorHistoryStore;
use App\Services\EditorHistory\SessionHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SessionHistoryPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_is_isolated_by_user_page_and_editor_session(): void
    {
        $store = app(EditorHistoryStore::class);
        $store->put(1, 7, 'session-a', ['pointer' => 0, 'entries' => [['state' => 'A']]]);

        $this->assertNotNull($store->get(1, 7, 'session-a'));
        $this->assertNull($store->get(1, 7, 'session-b'));
        $this->assertNull($store->get(2, 7, 'session-a'));
        $this->assertNull($store->get(1, 8, 'session-a'));
    }

    public function test_each_editor_mount_gets_a_new_session_even_for_the_same_user_and_page(): void
    {
        $user = User::factory()->admin()->create();
        $page = Page::factory()->create();
        $tabA = Livewire::actingAs($user)->test(EditPage::class, ['record' => $page->getRouteKey()]);
        $tabB = Livewire::actingAs($user)->test(EditPage::class, ['record' => $page->getRouteKey()]);

        $this->assertNotSame($tabA->get('historySessionId'), $tabB->get('historySessionId'));
        $this->assertNotNull(app(EditorHistoryStore::class)->get(
            $user->getKey(),
            $page->getKey(),
            $tabA->get('historySessionId'),
        ));
        $this->assertNotNull(app(EditorHistoryStore::class)->get(
            $user->getKey(),
            $page->getKey(),
            $tabB->get('historySessionId'),
        ));
    }

    public function test_server_side_history_preserves_undo_redo_branching_identity_and_coalescing(): void
    {
        $history = app(SessionHistory::class);
        $session = (string) Str::uuid();
        $a = $this->state(['A', 'B', 'C']);
        $client = $history->initial(1, 9, $session, $a);
        $client = $history->capture(1, 9, $session, $this->state(['A', 'C']), 'blocks', 60, 6_291_456);

        $this->assertCount(2, $client['entries']);
        $this->assertSame('delete', $client['entries'][1]['kind']);

        $restored = $history->state(1, 9, $session, $client['entries'][0]['id']);
        $this->assertSame(['A', 'B', 'C'], $this->blockIds($restored));
        $history->movePointer(1, 9, $session, $client['entries'][0]['id']);
        $deleted = $history->state(1, 9, $session, $client['entries'][1]['id']);
        $this->assertSame(['A', 'C'], $this->blockIds($deleted));

        $history->movePointer(1, 9, $session, $client['entries'][0]['id']);
        $branch = $history->capture(1, 9, $session, $this->state(['A', 'B', 'C'], 'branch'), 'title', 60, 6_291_456);
        $this->assertCount(2, $branch['entries']);
        $this->assertSame(1, $branch['pointer']);

        $coalesced = $history->capture(1, 9, $session, $this->state(['A', 'B', 'C'], 'branch 2'), 'title', 60, 6_291_456);
        $this->assertCount(2, $coalesced['entries']);
        $this->assertSame($branch['entries'][1]['id'], $coalesced['entries'][1]['id']);
    }

    public function test_checkpoint_count_and_total_snapshot_bytes_are_bounded_without_breaking_pointer(): void
    {
        $history = app(SessionHistory::class);
        $session = (string) Str::uuid();
        $history->initial(1, 10, $session, ['title' => '0', 'content' => str_repeat('x', 90_000)]);

        for ($i = 1; $i <= 75; $i++) {
            $client = $history->capture(1, 10, $session, [
                'title' => (string) $i,
                'content' => str_repeat(chr(65 + ($i % 20)), 90_000),
            ], "field.{$i}", 60, 1_048_576);
        }

        $stored = app(EditorHistoryStore::class)->get(1, 10, $session);
        $bytes = array_sum(array_map(fn (array $entry): int => strlen($entry['state']), $stored['entries']));

        $this->assertLessThanOrEqual(60, count($stored['entries']));
        $this->assertLessThanOrEqual(1_048_576, $bytes);
        $this->assertSame(count($stored['entries']) - 1, $stored['pointer']);
        $this->assertSame($stored['pointer'], $client['pointer']);
    }

    public function test_cache_ttl_expiration_is_graceful_and_refreshes_on_activity(): void
    {
        config()->set('cms.page_editor_history_ttl_seconds', 300);
        $history = app(SessionHistory::class);
        $session = (string) Str::uuid();
        $client = $history->initial(1, 11, $session, ['title' => 'A']);

        $this->travel(4)->minutes();
        $this->assertNotNull($history->state(1, 11, $session, $client['entries'][0]['id']));
        $this->travel(4)->minutes();
        $this->assertNotNull(app(EditorHistoryStore::class)->get(1, 11, $session));
        $this->travel(6)->minutes();
        $this->assertNull(app(EditorHistoryStore::class)->get(1, 11, $session));
    }

    public function test_large_page_keeps_full_snapshots_server_side_and_livewire_state_metadata_only(): void
    {
        $history = app(SessionHistory::class);
        $session = (string) Str::uuid();
        $state = ['title' => 'Large page', 'content' => '', 'blocks' => $this->largeBlocks()];
        $client = $history->initial(1, 12, $session, $state);

        for ($i = 1; $i <= 59; $i++) {
            $state['title'] = "Large page {$i}";
            $client = $history->capture(1, 12, $session, $state, "field.{$i}", 60, 20 * 1024 * 1024);
        }

        $metadata = $client['entries'];
        $stored = app(EditorHistoryStore::class)->get(1, 12, $session);
        $metadataBytes = strlen(json_encode([
            'historySessionId' => $session,
            'sessionHistoryPointer' => $client['pointer'],
            'sessionHistoryEntries' => $metadata,
        ]));
        $snapshotBytes = array_sum(array_map(fn (array $entry): int => strlen($entry['state']), $stored['entries']));

        $this->assertCount(60, $metadata);
        $this->assertTrue(collect($metadata)->every(fn (array $entry): bool => ! array_key_exists('state', $entry)));
        $this->assertGreaterThan(5 * 1024 * 1024, $snapshotBytes);
        $this->assertLessThan(16 * 1024, $metadataBytes);
        $this->assertSame(59, $client['pointer']);
    }

    public function test_store_write_failure_does_not_corrupt_editor_data(): void
    {
        $this->app->bind(EditorHistoryStore::class, fn () => new class implements EditorHistoryStore
        {
            public function get(int $userId, int $pageId, string $sessionId): ?array
            {
                return null;
            }

            public function put(int $userId, int $pageId, string $sessionId, array $history): void
            {
                throw new RuntimeException('offline');
            }

            public function forget(int $userId, int $pageId, string $sessionId): void {}
        });
        $user = User::factory()->admin()->create();
        $page = Page::factory()->create(['title' => 'Still editable']);

        Livewire::actingAs($user)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertSet('data.title', 'Still editable')
            ->assertSet('sessionHistoryAvailable', false)
            ->set('data.title', 'Editing continues')
            ->assertSet('data.title', 'Editing continues');
    }

    private function state(array $ids, string $title = 'page'): array
    {
        return ['title' => $title, 'blocks' => array_map(fn (string $id): array => [
            'type' => 'custom_html',
            'data' => ['block_id' => $id, 'content' => ['html' => "<p>{$id}</p>"]],
        ], $ids)];
    }

    private function blockIds(array $state): array
    {
        return array_column(array_column($state['blocks'], 'data'), 'block_id');
    }

    private function largeBlocks(): array
    {
        return array_map(fn (int $i): array => [
            'type' => ['hero', 'cta', 'feature_grid', 'faq'][$i % 4],
            'data' => [
                'block_id' => (string) Str::ulid(),
                'schema_version' => 2,
                'content' => [
                    'title' => "Block {$i}",
                    'description' => str_repeat('Realistic nested content ', 120),
                    'items' => array_map(fn (int $j): array => [
                        'title' => "Item {$j}",
                        'body' => str_repeat('Nested item content ', 80),
                        'media' => ['source_id' => $i * 10 + $j, 'url' => "/media/{$i}-{$j}.jpg"],
                    ], range(1, 6)),
                ],
                'settings' => ['theme' => 'light'],
            ],
        ], range(1, 25));
    }
}
