<?php

namespace Tests\Feature;

use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource\Pages\CreateTemplate;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlockEditorHydratorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_and_template_editors_share_identity_hydration_without_persisting_on_open(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [$this->legacyHero('Page')]]);
        $template = Template::query()->create([
            'title' => 'Template', 'slug' => 'template', 'type' => 'page', 'status' => 'draft',
            'blocks' => [$this->legacyHero('Template')],
        ]);
        $pageBefore = $page->blocks;
        $templateBefore = $template->blocks;

        $pageComponent = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $templateComponent = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);

        foreach ([$pageComponent, $templateComponent] as $component) {
            $blocks = $component->get('data')['blocks'];
            $uuid = array_key_first($blocks);
            $this->assertIsString($uuid);
            $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $blocks[$uuid]['data']['block_id']);
            $this->assertArrayNotHasKey('content', $blocks[$uuid]['data']);
            $this->assertArrayNotHasKey('settings', $blocks[$uuid]['data']);
        }

        $this->assertSame($pageBefore, $page->fresh()->blocks);
        $this->assertSame($templateBefore, $template->fresh()->blocks);
    }

    public function test_existing_and_duplicate_ids_are_preserved_or_repaired_in_form_state_only(): void
    {
        $this->actingAs(User::factory()->create());
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $page = Page::factory()->create(['blocks' => [
            $this->legacyHero('First', $id),
            $this->legacyHero('Second', $id),
        ]]);

        $blocks = array_values(Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->get('data')['blocks']);

        $this->assertSame($id, $blocks[0]['data']['block_id']);
        $this->assertNotSame($id, $blocks[1]['data']['block_id']);
        $this->assertSame($id, $page->fresh()->blocks[0]['data']['block_id']);
        $this->assertSame($id, $page->fresh()->blocks[1]['data']['block_id']);
    }

    public function test_empty_page_and_template_blocks_open_safely(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => []]);
        $template = Template::query()->create([
            'title' => 'Empty', 'slug' => 'empty', 'type' => 'page', 'status' => 'draft', 'blocks' => [],
        ]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->assertSet('data.blocks', []);
        Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->assertSet('data.blocks', []);
    }

    public function test_create_page_and_template_persist_new_block_identity(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Identity Page',
                'slug' => 'identity-page',
                'template' => 'default',
                'status' => 'draft',
                'blocks' => [$this->legacyHero('Page')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(CreateTemplate::class)
            ->fillForm([
                'title' => 'Identity Template',
                'slug' => 'identity-template',
                'type' => 'page',
                'status' => 'draft',
                'blocks' => [$this->legacyHero('Template')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        foreach ([
            Page::query()->where('slug', 'identity-page')->firstOrFail()->blocks,
            Template::query()->where('slug', 'identity-template')->firstOrFail()->blocks,
        ] as $blocks) {
            $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $blocks[0]['data']['block_id']);
            $this->assertArrayNotHasKey('schema_version', $blocks[0]['data']);
            $this->assertArrayNotHasKey('content', $blocks[0]['data']);
        }
    }

    public function test_edit_save_repairs_clone_duplicates_and_remains_stable_after_reload_and_reorder(): void
    {
        $this->actingAs(User::factory()->create());
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $page = Page::factory()->create(['blocks' => [
            $this->legacyHero('Original', $id),
            $this->legacyHero('Clone 1', $id),
            $this->legacyHero('Clone 2', $id),
            $this->legacyHero('Clone 3', $id),
        ]]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $component->call('save')->assertHasNoFormErrors();
        $saved = $page->fresh()->blocks;
        $ids = array_column(array_column($saved, 'data'), 'block_id');

        $this->assertSame($id, $ids[0]);
        $this->assertCount(4, array_unique($ids));
        $this->assertSame(['Original', 'Clone 1', 'Clone 2', 'Clone 3'], array_column(array_column($saved, 'data'), 'title'));

        $reloaded = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $state = $reloaded->get('data')['blocks'];
        $reloaded->set('data.blocks', array_reverse($state, true))->call('save')->assertHasNoFormErrors();

        $this->assertSame(array_reverse($ids), array_column(array_column($page->fresh()->blocks, 'data'), 'block_id'));
    }

    public function test_template_edit_save_repairs_and_persists_identity_idempotently(): void
    {
        $this->actingAs(User::factory()->create());
        $id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $template = Template::query()->create([
            'title' => 'Template', 'slug' => 'template-save', 'type' => 'page', 'status' => 'draft',
            'blocks' => [$this->legacyHero('First', $id), $this->legacyHero('Second', $id)],
        ]);

        Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();
        $once = $template->fresh()->blocks;

        Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($once, $template->fresh()->blocks);
        $this->assertCount(2, array_unique(array_column(array_column($once, 'data'), 'block_id')));
    }

    public function test_v2_template_uses_v2_schema_even_when_global_upgrade_flag_is_off(): void
    {
        $this->actingAs(User::factory()->create());
        $v2 = app(HeroDataNormalizer::class)->normalize([
            'template' => 'hero_1', 'title' => 'V2 title', 'overlay_opacity' => 37,
            'image_width_value' => 80, 'image_width_unit' => '%',
            'hero_1_social_links' => [['label' => 'Social', 'url' => '/social']],
        ]);
        $template = Template::query()->create([
            'title' => 'V2 Template', 'slug' => 'v2-template', 'type' => 'page', 'status' => 'draft',
            'blocks' => [['type' => 'hero', 'data' => $v2]],
        ]);
        $before = $template->blocks;
        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $rawBlocks = $component->instance()->form->getRawState()['blocks'];
        $raw = $rawBlocks[array_key_first($rawBlocks)]['data'];

        $this->assertSame('V2 title', $raw['content']['title']);
        $this->assertSame(37, $raw['settings']['overlay_opacity']);
        $this->assertSame(80, $raw['settings']['media']['desktop']['width']['value']);
        $this->assertSame('/social', array_values($raw['content']['social_links'])[0]['url']);
        $this->assertFalse(array_key_exists('title', $raw));
        $this->assertSame(2, $raw['schema_version']);
        $component->call('save')->assertHasNoFormErrors();
        $saved = $template->fresh()->blocks;

        $this->assertNotSame($before, $saved);
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $saved[0]['data']['block_id']);
        $this->assertSame('/social', $saved[0]['data']['content']['social_links'][0]['url']);
        $this->assertSame([], $saved[0]['data']['settings']['background_effect']['settings']);
        Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame($saved, $template->fresh()->blocks);
    }

    private function legacyHero(string $title, ?string $id = null): array
    {
        return ['type' => 'hero', 'data' => array_filter([
            'block_id' => $id, 'template' => 'hero_1', 'title' => $title, 'hero_1_theme' => 'image',
            'stats' => [['value' => '1', 'label' => 'One']],
            'selector_items' => [['label' => 'A', 'url' => '/a']],
            'hero_1_social_links' => [['label' => 'Social', 'url' => '/social']],
        ], fn ($value): bool => $value !== null)];
    }
}
