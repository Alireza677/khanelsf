<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPickerService;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Form;
use App\Models\Page;
use App\Models\Product;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Fixtures\ActionPickerLifecycleComponent;
use Tests\Fixtures\DestinationPickerRepeaterComponent;
use Tests\TestCase;

class DestinationBrowserTest extends TestCase
{
    use RefreshDatabase;

    public function test_browsing_is_admin_only_and_the_old_search_response_is_preserved(): void
    {
        $endpoint = route('admin.internal-links.search');
        $this->getJson($endpoint.'?target=form')->assertUnauthorized();
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->getJson($endpoint.'?target=form')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create(['status' => 'inactive']))
            ->getJson($endpoint.'?target=form')->assertForbidden();
        $page = Page::factory()->published()->create(['title' => 'Legacy Search']);
        $this->actingAs(User::factory()->admin()->create())
            ->getJson($endpoint.'?q=Legacy')->assertOk()->assertJsonPath('0.reference_id', $page->id);
        $this->getJson($endpoint.'?target=unknown')->assertUnprocessable();
        $this->getJson($endpoint.'?target=page&page=0')->assertUnprocessable();
        $this->getJson($endpoint.'?target=page&selected='.$page->id)
            ->assertOk()->assertJsonPath('label', 'Legacy Search');
    }

    public function test_every_allowed_record_is_reachable_without_an_initial_search_for_every_target(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['page', 'project', 'product', 'service', 'form'] as $type) {
            $ids = [];
            for ($i = 0; $i < 43; $i++) {
                $attributes = ['slug' => $type.'-'.$i];
                $record = match ($type) {
                    'page' => Page::factory()->published()->create($attributes),
                    'project' => Project::factory()->published()->create($attributes),
                    'product' => Product::factory()->published()->create($attributes),
                    'service' => Service::create($attributes + ['name' => sprintf('Service %03d', $i), 'status' => 'active']),
                    'form' => Form::create($attributes + ['name' => sprintf('Form %03d', $i), 'status' => 'published']),
                };
                $ids[] = $record->id;
            }
            $seen = [];
            for ($page = 1; $page <= 3; $page++) {
                $response = $this->getJson(route('admin.internal-links.search', ['target' => $type, 'page' => $page]))
                    ->assertOk()->assertJsonPath('total', 43)->assertJsonPath('last_page', 3);
                $this->assertLessThanOrEqual(20, count($response['items']));
                $seen = [...$seen, ...array_column($response['items'], 'reference_id')];
                $this->assertNotContains(false, array_column($response['items'], 'selectable'));
            }
            $this->assertCount(43, array_unique($seen));
            $this->assertEqualsCanonicalizing($ids, $seen);
        }
    }

    public function test_search_supports_title_slug_id_sku_literal_wildcards_and_project_category(): void
    {
        $picker = app(ActionPickerService::class);
        $page = Page::factory()->published()->create(['title' => '100% destination', 'slug' => 'unique-slug']);
        Page::factory()->draft()->create(['title' => '100% hidden']);
        foreach (['100%', 'unique-slug', (string) $page->id] as $query) {
            $this->assertSame([$page->id], array_column($picker->browseTargets('page', $query)->items(), 'reference_id'));
        }
        $product = Product::factory()->published()->create(['sku' => 'SKU-UNIQUE-321']);
        $this->assertSame([$product->id], array_column($picker->browseTargets('product', 'SKU-UNIQUE')->items(), 'reference_id'));
        $category = ProjectCategory::factory()->create(['status' => 'active']);
        $project = Project::factory()->published()->create(['project_category_id' => $category->id]);
        Project::factory()->published()->create();
        $this->assertSame([$project->id], array_column($picker->browseTargets('project', '', 1, ['category_id' => $category->id])->items(), 'reference_id'));
    }

    public function test_publication_modules_invalid_destinations_and_service_ancestry_remain_enforced(): void
    {
        Page::factory()->draft()->create();
        Page::factory()->published()->create(['published_at' => now()->addDay()]);
        $invalid = Page::factory()->published()->create(['slug' => '']);
        $picker = app(ActionPickerService::class);
        $rows = $picker->browseTargets('page')->items();
        $this->assertCount(1, $rows);
        $this->assertSame($invalid->id, $rows[0]['reference_id']);
        $this->assertFalse($rows[0]['selectable']);
        $root = Service::create(['name' => 'Grandparent', 'slug' => 'grandparent', 'status' => 'active']);
        $parent = Service::create(['name' => 'Parent', 'slug' => 'parent', 'status' => 'active', 'parent_id' => $root->id]);
        $child = Service::create(['name' => 'Child', 'slug' => 'child', 'status' => 'active', 'parent_id' => $parent->id]);
        $this->assertSame('Grandparent / Parent', $picker->browseTargets('service', 'child')->items()[0]['details']);
        $this->assertTrue($picker->referenceIsAvailable('service', $child->id));
        foreach (['projects_enabled', 'shop_enabled'] as $setting) {
            app(SettingsService::class)->set($setting, false);
        }
        $this->assertSame([], $picker->browseTargets('project')->items());
        $this->assertSame([], $picker->browseTargets('product')->items());
        app(SettingsService::class)->set('public_services_enabled', false);
        $this->assertSame([], $picker->browseTargets('service')->items());
    }

    public function test_draft_forms_are_visible_but_cannot_be_submitted_and_both_displays_round_trip(): void
    {
        $published = Form::create(['name' => 'Public', 'slug' => 'public', 'status' => 'published']);
        $draft = Form::create(['name' => 'Draft', 'slug' => 'draft', 'status' => 'draft']);
        $rows = collect(app(ActionPickerService::class)->browseTargets('form')->items())->keyBy('reference_id');
        $this->assertFalse($rows[$draft->id]['selectable']);
        foreach (['modal', 'page'] as $display) {
            $component = Livewire::test(ActionPickerLifecycleComponent::class, ['action' => ['type' => 'form', 'reference_id' => $published->id, 'display' => $display]])
                ->call('save')->assertHasNoErrors()->assertSet('saved.action.display', $display);
            $saved = $component->get('saved.action');
            $this->assertSame(['schema_version', 'type', 'reference_id', 'display', 'open_in_new_tab'], array_keys($saved));
            Livewire::test(ActionPickerLifecycleComponent::class, ['action' => $saved])->call('save')->assertSet('saved.action', $saved);
        }
        Livewire::test(ActionPickerLifecycleComponent::class, ['action' => ['type' => 'form', 'reference_id' => $draft->id, 'display' => 'modal']])
            ->call('save')->assertHasErrors(['data.action.reference_id']);
    }

    public function test_multiple_actions_reordered_and_cloned_cards_keep_their_own_references(): void
    {
        $first = Page::factory()->published()->create();
        $second = Page::factory()->published()->create();
        $action = fn ($id) => ['type' => 'page', 'reference_id' => $id];
        $component = Livewire::test(DestinationPickerRepeaterComponent::class, ['data' => [
            'primary' => $action($first->id), 'secondary' => $action($second->id),
            'cards' => [['action' => $action($first->id)], ['action' => $action($second->id)]],
        ]]);
        $keys = array_keys($component->get('data.cards'));
        $component->set('data.primary.type', 'form')->assertSet('data.primary.reference_id', null)
            ->assertSet('data.secondary.reference_id', $second->id)
            ->set('data.primary.type', null);
        $component->callFormComponentAction('cards', 'clone', arguments: ['item' => $keys[0]]);
        $cards = $component->get('data.cards');
        $this->assertCount(3, $cards);
        $clone = array_values(array_diff(array_keys($cards), $keys))[0];
        $component->set("data.cards.{$clone}.action.reference_id", $second->id)
            ->assertSet("data.cards.{$keys[0]}.action.reference_id", $first->id)
            ->callFormComponentAction('cards', 'reorder', arguments: ['items' => array_reverse(array_keys($cards))])
            ->call('save')->assertHasNoErrors();
        $this->assertSame([$second->id, $second->id, $first->id], array_column(array_column($component->get('saved.cards'), 'action'), 'reference_id'));
        $component->set("data.cards.{$keys[0]}.action.type", null)->call('save')->assertHasNoErrors();
        $this->assertNull($component->get('saved.cards')[2]['action']);
    }

    public function test_real_page_and_template_editors_save_reload_and_clone_reference_actions(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $first = Page::factory()->published()->create();
        $second = Page::factory()->published()->create();
        $blocks = [['type' => 'cta', 'data' => [
            'block_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'schema_version' => 2, 'template' => 'image',
            'content' => ['title' => 'Choose destinations',
                'primary_cta' => ['label' => 'First', 'action' => ['type' => 'page', 'reference_id' => $first->id]],
                'secondary_cta' => ['label' => 'Second', 'action' => ['type' => 'page', 'reference_id' => $second->id]],
            ],
        ]]];
        $page = Page::factory()->create(['blocks' => $blocks]);
        $template = Template::create(['title' => 'Reference template', 'slug' => 'reference-template', 'type' => 'page', 'status' => 'draft', 'blocks' => $blocks]);
        foreach ([[EditPage::class, $page], [EditTemplate::class, $template]] as [$class, $record]) {
            $component = Livewire::test($class, ['record' => $record->getRouteKey()]);
            $key = array_key_first($component->get('data.blocks'));
            $path = "data.blocks.{$key}.data.content.primary_cta.action.reference_id";
            $component->assertSeeHtml('data-destination-browser')
                ->set($path, $second->id)
                ->assertSet("data.blocks.{$key}.data.content.secondary_cta.action.reference_id", $second->id);
            if ($class === EditPage::class) {
                $component->call('undoEditorHistory');
                $key = array_key_first($component->get('data.blocks'));
                $component->assertSet("data.blocks.{$key}.data.content.primary_cta.action.reference_id", $first->id)
                    ->call('redoEditorHistory');
                $key = array_key_first($component->get('data.blocks'));
                $component->assertSet("data.blocks.{$key}.data.content.primary_cta.action.reference_id", $second->id);
            }
            $component->callFormComponentAction('blocks', 'clone', arguments: ['item' => $key]);
            $clone = array_key_last($component->get('data.blocks'));
            $component->set("data.blocks.{$clone}.data.content.primary_cta.action.reference_id", $first->id)
                ->call('save')->assertHasNoFormErrors();
            $saved = $record->fresh()->blocks;
            $this->assertSame($second->id, data_get($saved, '0.data.content.primary_cta.action.reference_id'));
            $this->assertSame($first->id, data_get($saved, '1.data.content.primary_cta.action.reference_id'));
            $this->assertArrayNotHasKey('url', data_get($saved, '0.data.content.primary_cta.action'));
            $reloaded = Livewire::test($class, ['record' => $record->getRouteKey()]);
            $reloadedKey = array_key_first($reloaded->get('data.blocks'));
            $reloaded->assertSet("data.blocks.{$reloadedKey}.data.content.primary_cta.action.reference_id", $second->id);
            if ($class === EditPage::class) {
                $baseline = $record->revisions()->orderBy('revision_number')->firstOrFail();
                $reloaded->call('selectPageRevision', $baseline->id)->call('applySelectedPageRevision');
                $restoredKey = array_key_first($reloaded->get('data.blocks'));
                $reloaded->assertSet("data.blocks.{$restoredKey}.data.content.primary_cta.action.reference_id", $first->id)
                    ->call('save')->assertHasNoFormErrors();
                $this->assertSame($first->id, data_get($record->fresh()->blocks, '0.data.content.primary_cta.action.reference_id'));
            }
        }
    }
}
