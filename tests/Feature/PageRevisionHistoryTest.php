<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\Revision;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PageRevisionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_first_changed_save_creates_baseline_and_new_revision_while_no_op_save_does_not_duplicate(): void
    {
        $user = User::factory()->admin()->create();
        $page = $this->page('Original');

        $component = Livewire::actingAs($user)->test(EditPage::class, ['record' => $page->getRouteKey()]);
        $component->set('data.title', 'Changed')->call('save')->assertHasNoFormErrors();

        $this->assertSame([1, 2], $page->revisions()->pluck('revision_number')->all());
        $this->assertSame('Original', $page->revisions()->first()->snapshot['title']);
        $this->assertSame('Changed', $page->revisions()->latest('revision_number')->first()->snapshot['title']);
        $this->assertSame($user->getKey(), $page->revisions()->latest('revision_number')->first()->created_by);

        $component->call('save')->assertHasNoFormErrors();

        $this->assertSame(2, $page->revisions()->count());
        $this->assertArrayNotHasKey('status', $page->revisions()->first()->snapshot);
        $this->assertArrayNotHasKey('published_at', $page->revisions()->first()->snapshot);
    }

    public function test_edit_page_exposes_the_rtl_history_slide_over_with_separate_tabs(): void
    {
        $user = User::factory()->admin()->create();
        $page = $this->page('History UI');

        Livewire::actingAs($user)
            ->test(EditPage::class, ['record' => $page->getRouteKey()])
            ->assertActionExists('history')
            ->mountAction('history')
            ->assertSee('تاریخچه')
            ->assertSee('عملیات')
            ->assertSee('رونوشت‌ها')
            ->assertSeeHtml('dir="rtl"');
    }

    public function test_revision_restore_updates_editor_only_then_appends_immutable_restore_revision(): void
    {
        $user = User::factory()->admin()->create();
        $page = $this->page('Version 1', status: 'published');
        $component = Livewire::actingAs($user)->test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component->set('data.title', 'Version 2')->call('save')->assertHasNoFormErrors();
        $component->set('data.title', 'Version 3')->call('save')->assertHasNoFormErrors();
        $baseline = $page->revisions()->where('revision_number', 1)->firstOrFail();

        $component
            ->call('selectPageRevision', $baseline->getKey())
            ->call('applySelectedPageRevision')
            ->assertSet('data.title', 'Version 1')
            ->assertSet('data.status', 'published');

        $restoredBlocks = array_values($component->get('data')['blocks']);
        $this->assertSame(125, data_get($restoredBlocks, '0.data.content.media.source_id'));

        $this->assertSame('Version 3', $page->fresh()->title);
        $this->assertSame(3, $page->revisions()->count());

        $component->call('save')->assertHasNoFormErrors();

        $this->assertSame('Version 1', $page->fresh()->title);
        $this->assertSame([1, 2, 3, 4], $page->revisions()->pluck('revision_number')->all());
        $this->assertSame($baseline->getKey(), $page->revisions()->latest('revision_number')->first()->restored_from_revision_id);
        $this->assertSame(self::BLOCK_ID, data_get($page->fresh()->blocks, '0.data.block_id'));
    }

    public function test_revision_queries_are_isolated_to_the_page_being_edited(): void
    {
        $user = User::factory()->admin()->create();
        $pageA = $this->page('Page A');
        $pageB = $this->page('Page B');
        $foreignRevision = $pageB->revisions()->create([
            'revision_number' => 1,
            'snapshot' => ['title' => 'Secret'],
            'checksum' => str_repeat('a', 64),
            'event' => 'baseline',
        ]);

        $component = Livewire::actingAs($user)->test(EditPage::class, ['record' => $pageA->getRouteKey()]);

        $this->assertSame([], $component->instance()->revisionRows());
        $this->expectException(ModelNotFoundException::class);
        $component->call('selectPageRevision', $foreignRevision->getKey());
    }

    public function test_revision_list_query_omits_snapshot_and_is_bounded(): void
    {
        $user = User::factory()->admin()->create();
        $page = $this->page('Bounded');

        foreach (range(1, 35) as $number) {
            $page->revisions()->create([
                'revision_number' => $number,
                'snapshot' => ['title' => "Version {$number}"],
                'checksum' => hash('sha256', (string) $number),
                'event' => 'save',
            ]);
        }

        $component = Livewire::actingAs($user)->test(EditPage::class, ['record' => $page->getRouteKey()]);
        $rows = $component->instance()->revisionRows();

        $this->assertCount(30, $rows);
        $this->assertSame(35, $rows[0]['number']);
        $this->assertTrue($component->instance()->hasMoreRevisions());
        $component->call('loadMoreRevisions');
        $this->assertCount(35, $component->instance()->revisionRows());
        $this->assertFalse($component->instance()->hasMoreRevisions());
        $this->assertFalse(Revision::query()->select(['id'])->firstOrFail()->relationLoaded('revisionable'));
    }

    public function test_session_operations_undo_redo_and_branching_preserve_block_identity(): void
    {
        $user = User::factory()->admin()->create();
        $page = $this->page('Operations');
        $component = Livewire::actingAs($user)->test(EditPage::class, ['record' => $page->getRouteKey()]);
        $initialBlocks = $component->get('data')['blocks'];

        $component->set('data.blocks', []);
        $this->assertTrue($component->instance()->canUndoEditorHistory());

        $component->call('undoEditorHistory');
        $restored = array_values($component->get('data')['blocks']);
        $this->assertSame(self::BLOCK_ID, data_get($restored, '0.data.block_id'));

        $component->call('redoEditorHistory')->assertSet('data.blocks', []);
        $component->call('undoEditorHistory');
        $component->set('data.title', 'Branched');

        $this->assertFalse($component->instance()->canRedoEditorHistory());
        $this->assertSame(self::BLOCK_ID, data_get(array_values($initialBlocks), '0.data.block_id'));
    }

    public function test_structural_builder_actions_create_checkpoints_and_field_typing_is_coalesced(): void
    {
        $user = User::factory()->admin()->create();
        $page = $this->page('Structural');
        $component = Livewire::actingAs($user)->test(EditPage::class, ['record' => $page->getRouteKey()]);

        $component
            ->callFormComponentAction('blocks', 'add', arguments: ['block' => 'custom_html'])
            ->assertHasNoFormComponentActionErrors();

        $this->assertSame('add', $component->get('sessionHistoryEntries')[1]['kind']);
        $this->assertArrayNotHasKey('state', $component->get('sessionHistoryEntries')[1]);
        $this->assertCount(2, $component->get('data')['blocks']);

        $component->call('undoEditorHistory');
        $this->assertCount(1, $component->get('data')['blocks']);
        $component->call('redoEditorHistory');
        $this->assertCount(2, $component->get('data')['blocks']);

        $beforeTyping = count($component->get('sessionHistoryEntries'));
        $component->set('data.title', 'S')->set('data.title', 'Session title');

        $this->assertSame($beforeTyping + 1, count($component->get('sessionHistoryEntries')));
        $this->assertSame('ویرایش تنظیمات برگه', $component->get('sessionHistoryEntries')[$beforeTyping]['label']);
    }

    private function page(string $title, string $status = 'draft'): Page
    {
        return Page::factory()->create([
            'title' => $title,
            'status' => $status,
            'published_at' => $status === 'published' ? now()->subDay() : null,
            'blocks' => [[
                'type' => 'hero',
                'data' => [
                    'block_id' => self::BLOCK_ID,
                    'schema_version' => 2,
                    'template' => 'default',
                    'content' => [
                        'title' => 'Hero',
                        'media' => ['source_id' => 125, 'url' => '/media.jpg'],
                    ],
                    'settings' => [],
                ],
            ]],
        ]);
    }
}
