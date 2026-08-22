<?php

namespace Tests\Feature;

use App\CMS\Actions\Filament\ActionPicker;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\FeatureGrid\FeatureGridBlock;
use App\CMS\Blocks\FeatureGrid\FeatureGridRuntime;
use App\CMS\Blocks\Hero\HeroBlock;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Form;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\ViewField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class FeatureGridActionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    public function test_registered_block_is_the_shared_page_and_template_schema_source(): void
    {
        $registry = app(BlockRegistry::class);
        $block = $registry->find('feature_grid');

        $this->assertInstanceOf(FeatureGridBlock::class, $block);
        $this->assertSame('partials.blocks.feature_grid', $block->renderView([]));
        $this->assertSame(1, $block->version());

        foreach ([HeroBlock::CONTEXT_PAGE, HeroBlock::CONTEXT_TEMPLATE] as $context) {
            $items = collect($block->filamentSchema($context))
                ->first(fn ($component): bool => $component instanceof Repeater
                    && $component->getStatePath(false) === 'content.items');
            $picker = collect($items?->getChildComponents() ?? [])
                ->first(fn ($component): bool => $component instanceof ActionPicker);
            $description = collect($items?->getChildComponents() ?? [])
                ->first(fn ($component): bool => $component instanceof RichEditor
                    && $component->getStatePath(false) === 'description');
            $image = collect($items?->getChildComponents() ?? [])
                ->first(fn ($component): bool => $component instanceof ViewField
                    && $component->getStatePath(false) === 'image');

            $this->assertInstanceOf(ActionPicker::class, $picker);
            $this->assertInstanceOf(RichEditor::class, $description);
            $this->assertInstanceOf(ViewField::class, $image);
            $this->assertSame('full', $image->getColumnSpan('default'));
            $this->assertSame('action', $picker->getStatePath(false));
            $this->assertSame([
                'custom_url',
                'page',
                'project',
                'product',
                'service',
                'form',
                'anchor',
                'email',
                'phone',
            ], array_keys($picker->getTypeOptions()));
        }
    }

    public function test_page_editor_dual_reads_legacy_and_writes_canonical_without_write_on_open(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [[
            'type' => 'feature_grid',
            'data' => $this->legacyGrid(),
        ]]]);
        $before = $page->blocks;
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $blockKey = array_key_first($component->get('data.blocks'));
        $items = $component->get("data.blocks.{$blockKey}.data.content.items");
        $itemKey = array_key_first($items);

        $component
            ->assertSet("data.blocks.{$blockKey}.data.schema_version", 1)
            ->assertSet(
                "data.blocks.{$blockKey}.data.content.items.{$itemKey}.action.type",
                'custom_url',
            )
            ->assertSet(
                "data.blocks.{$blockKey}.data.content.items.{$itemKey}.action.value",
                '/legacy-feature',
            );
        $this->assertSame($before, $page->fresh()->blocks);

        $component
            ->set("data.blocks.{$blockKey}.data.content.section_title", 'Edited Grid')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame(
            ['block_id', 'schema_version', 'template', 'content', 'settings'],
            array_keys($saved),
        );
        $this->assertSame(self::ID, $saved['block_id']);
        $this->assertSame('Edited Grid', $saved['content']['section_title']);
        $this->assertSame('/legacy-feature', $saved['content']['items'][0]['action']['value']);
        $this->assertArrayNotHasKey('button_url', $saved['content']['items'][0]);
    }

    public function test_item_rich_text_survives_page_save_reload_without_changing_shape_or_action(): void
    {
        $this->actingAs(User::factory()->create());
        $html = '<p>First paragraph</p><p><strong>Bold</strong> and <em>italic</em></p><ul><li>Item</li></ul><p><a href="/details">Details</a></p>';
        $page = Page::factory()->create(['blocks' => [[
            'type' => 'feature_grid',
            'data' => $this->canonicalGrid([[
                'title' => 'Rich card',
                'description' => $html,
                'button_label' => 'Card action',
                'action' => ['type' => 'custom_url', 'value' => '/card-action'],
            ]]),
        ]]]);
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $blockKey = array_key_first($component->get('data.blocks'));
        $itemKey = array_key_first($component->get("data.blocks.{$blockKey}.data.content.items"));

        $component
            ->assertSet("data.blocks.{$blockKey}.data.content.items.{$itemKey}.description", $html)
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame($html, $saved['content']['items'][0]['description']);
        $this->assertSame('/card-action', $saved['content']['items'][0]['action']['value']);
        $this->assertSame(1, $saved['schema_version']);
        $reloaded = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $reloadedBlockKey = array_key_first($reloaded->get('data.blocks'));
        $reloadedItemKey = array_key_first($reloaded->get("data.blocks.{$reloadedBlockKey}.data.content.items"));
        $this->assertSame(
            $html,
            $reloaded->get("data.blocks.{$reloadedBlockKey}.data.content.items.{$reloadedItemKey}.description"),
        );
    }

    public function test_template_editor_has_the_same_hydration_and_save_contract(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $this->actingAs(User::factory()->create());
        $template = Template::query()->create([
            'title' => 'Feature Grid Template',
            'slug' => 'feature-grid-template',
            'type' => 'page',
            'status' => 'draft',
            'blocks' => [['type' => 'feature_grid', 'data' => $this->legacyGrid()]],
        ]);
        $before = $template->blocks;
        $component = Livewire::test(EditTemplate::class, [
            'record' => $template->getRouteKey(),
        ]);
        $blockKey = array_key_first($component->get('data.blocks'));

        $component->assertSet(
            "data.blocks.{$blockKey}.data.content.section_title",
            'Legacy Grid',
        );
        $this->assertSame($before, $template->fresh()->blocks);

        $component
            ->set("data.blocks.{$blockKey}.data.content.section_title", 'Template Grid')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $template->fresh()->blocks[0]['data'];
        $this->assertSame('Template Grid', $saved['content']['section_title']);
        $this->assertSame('custom_url', $saved['content']['items'][0]['action']['type']);
        $this->assertArrayNotHasKey('button_url', $saved['content']['items'][0]);
    }

    public function test_static_grid_renders_all_nine_targets_and_isolates_invalid_cards(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'feature-action-page']);
        $draftPage = Page::factory()->draft()->create(['slug' => 'feature-draft-page']);
        $project = Project::factory()->published()->create(['slug' => 'feature-project']);
        $product = Product::factory()->published()->create(['slug' => 'feature-product']);
        $service = Service::query()->create([
            'name' => 'Feature Service',
            'slug' => 'feature-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $modalForm = $this->form('modal');
        $pageForm = $this->form('page', 'feature-page-form');
        $actions = [
            'Custom' => ['type' => 'custom_url', 'value' => '/custom'],
            'Page' => ['type' => 'page', 'reference_id' => $page->getKey()],
            'Project' => ['type' => 'project', 'reference_id' => $project->getKey()],
            'Product' => ['type' => 'product', 'reference_id' => $product->getKey()],
            'Service' => ['type' => 'service', 'reference_id' => $service->getKey()],
            'Form' => [
                'type' => 'form',
                'reference_id' => $modalForm->getKey(),
                'display' => 'modal',
            ],
            'Anchor' => ['type' => 'anchor', 'value' => 'contact'],
            'Email' => ['type' => 'email', 'value' => 'info@example.com'],
            'Phone' => ['type' => 'phone', 'value' => '+989121234567'],
        ];
        $items = collect($actions)
            ->map(fn (array $action, string $label): array => [
                'title' => "{$label} card",
                'button_label' => $label,
                'action' => $action,
            ])
            ->values()
            ->all();
        $items[] = [
            'title' => 'Form page card',
            'button_label' => 'Form page',
            'action' => [
                'type' => 'form',
                'reference_id' => $pageForm->getKey(),
                'display' => 'page',
            ],
        ];
        $items[] = [
            'title' => 'Invalid card remains',
            'button_label' => 'Invalid action',
            'action' => ['type' => 'page', 'reference_id' => $draftPage->getKey()],
        ];
        $items[] = [
            'title' => 'Label only card',
            'button_label' => 'No action label',
        ];
        $html = $this->render($this->canonicalGrid($items));

        foreach (array_keys($actions) as $label) {
            $this->assertStringContainsString(">{$label}</", $html);
        }

        $this->assertStringContainsString('href="/custom"', $html);
        $this->assertStringContainsString($page->resolveNavigationUrl(), $html);
        $this->assertStringContainsString($project->resolveNavigationUrl(), $html);
        $this->assertStringContainsString($product->resolveNavigationUrl(), $html);
        $this->assertStringContainsString($service->resolveNavigationUrl(), $html);
        $this->assertStringContainsString('data-form-action-modal-url', $html);
        $this->assertStringContainsString(
            'action="'.route('forms.context', $pageForm->slug).'"',
            $html,
        );
        $this->assertStringContainsString('href="#contact"', $html);
        $this->assertStringContainsString('href="mailto:info@example.com"', $html);
        $this->assertStringContainsString('href="tel:+989121234567"', $html);
        $this->assertStringContainsString('Invalid card remains', $html);
        $this->assertStringNotContainsString('Invalid action', $html);
        $this->assertStringContainsString('Label only card', $html);
        $this->assertStringNotContainsString('No action label', $html);
    }

    public function test_feature_grid_renders_temporary_placeholder_action(): void
    {
        $html = $this->render($this->canonicalGrid([[
            'title' => 'Temporary card',
            'button_label' => 'Temporary feature action',
            'action' => [
                'type' => 'custom_url',
                'value' => '#',
            ],
        ]]));

        $this->assertStringContainsString('href="#"', $html);
        $this->assertStringContainsString('data-action-placeholder', $html);
        $this->assertStringContainsString('>Temporary feature action</a>', $html);
    }

    public function test_item_rich_text_and_legacy_text_use_shared_safe_renderer(): void
    {
        $rich = '<p>First paragraph</p><p>Second with <strong>bold</strong> and <em>italic</em>.</p><ol><li>One</li></ol><p><a href="/more">More</a></p><script>alert(1)</script>';
        $data = $this->canonicalGrid([
            ['title' => 'Rich', 'description' => $rich],
            ['title' => 'Legacy', 'description' => "First line\nsecond line\n\nSecond paragraph"],
            ['title' => 'Empty', 'description' => null],
        ]);

        $production = $this->render($data);
        $preview = $this->render($data, isPreview: true);

        foreach ([$production, $preview] as $html) {
            $this->assertStringContainsString('<p>First paragraph</p><p>Second with <strong>bold</strong> and <em>italic</em>.</p>', $html);
            $this->assertStringContainsString('<ol><li>One</li></ol>', $html);
            $this->assertStringContainsString('<a href="/more">More</a>', $html);
            $this->assertStringNotContainsString('<script', $html);
            $this->assertMatchesRegularExpression('/<p>First line<br\s*\/?>(?:\r?\n)?second line<\/p>/', $html);
            $this->assertStringContainsString('<p>Second paragraph</p>', $html);
            $this->assertSame(2, substr_count($html, 'block-card__description'));
        }
    }

    public function test_multiple_cards_preserve_new_tab_form_attribution_and_failure_isolation(): void
    {
        $firstForm = $this->form('modal', 'feature-form-one');
        $secondForm = $this->form('modal', 'feature-form-two');
        $html = $this->render($this->canonicalGrid([
            [
                'title' => 'External card',
                'button_label' => 'External',
                'action' => [
                    'type' => 'custom_url',
                    'value' => 'https://example.com',
                    'open_in_new_tab' => true,
                ],
            ],
            [
                'title' => 'First form',
                'button_label' => 'First modal',
                'action' => [
                    'type' => 'form',
                    'reference_id' => $firstForm->getKey(),
                    'display' => 'modal',
                ],
            ],
            [
                'title' => 'Second form',
                'button_label' => 'Second modal',
                'action' => [
                    'type' => 'form',
                    'reference_id' => $secondForm->getKey(),
                    'display' => 'modal',
                ],
            ],
        ]), ['page_id' => null, 'page_url' => '/feature-source']);

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('rel="noopener noreferrer"', $html);
        $this->assertSame(2, substr_count($html, 'data-form-action-modal-url'));
        $this->assertSame(2, substr_count($html, 'name="_token"'));
        $this->assertSame(2, substr_count($html, 'value="'.self::ID.'"'));
        $this->assertStringContainsString('value="/feature-source"', $html);
    }

    public function test_dynamic_projects_use_reference_actions_and_posts_keep_derived_compatibility(): void
    {
        Project::factory()->published()->create([
            'title' => 'Older Project',
            'published_at' => now()->subDays(2),
        ]);
        $latestProject = Project::factory()->published()->create([
            'title' => 'Latest Project',
            'published_at' => now()->subDay(),
        ]);
        $latestPost = Post::factory()->published()->create([
            'title' => 'Latest Post',
            'published_at' => now(),
        ]);
        $runtime = app(FeatureGridRuntime::class);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $projects = $runtime->prepare([
            'items_mode' => 'dynamic',
            'dynamic_source' => 'projects',
            'dynamic_rows' => 1,
            'dynamic_columns' => 1,
            'dynamic_button_label' => 'View Project',
            'dynamic_button_overrides' => [[
                'record_id' => $latestProject->getKey(),
                'button_label' => 'Exact Project',
            ]],
        ]);
        $queryCount = count(DB::getQueryLog());
        $posts = $runtime->prepare([
            'items_mode' => 'dynamic',
            'dynamic_source' => 'posts',
            'dynamic_rows' => 1,
            'dynamic_columns' => 1,
            'dynamic_button_label' => 'Read Post',
        ]);

        $this->assertCount(1, $projects['items']);
        $this->assertSame('Latest Project', $projects['items'][0]['title']);
        $this->assertSame('Exact Project', $projects['items'][0]['button_label']);
        $this->assertSame('project', $projects['items'][0]['action']['type']);
        $this->assertSame($latestProject->getKey(), $projects['items'][0]['action']['reference_id']);
        $this->assertArrayNotHasKey('button_url', $projects['items'][0]);
        $this->assertLessThanOrEqual(6, $queryCount);
        $this->assertSame('Latest Post', $posts['items'][0]['title']);
        $this->assertSame('custom_url', $posts['items'][0]['action']['type']);
        $this->assertSame(route('blog.show', $latestPost->slug), $posts['items'][0]['action']['value']);
    }

    public function test_preview_applies_entity_policy_without_breaking_form_modal_or_cards(): void
    {
        $page = Page::factory()->draft()->create();
        $service = Service::query()->create([
            'name' => 'Preview Service',
            'slug' => 'preview-feature-service',
            'status' => Service::STATUS_ACTIVE,
        ]);
        $form = $this->form('modal', 'preview-feature-form');
        $html = $this->render($this->canonicalGrid([
            [
                'title' => 'Draft page card',
                'button_label' => 'Preview page',
                'action' => ['type' => 'page', 'reference_id' => $page->getKey()],
            ],
            [
                'title' => 'Service card remains',
                'button_label' => 'Unavailable service',
                'action' => ['type' => 'service', 'reference_id' => $service->getKey()],
            ],
            [
                'title' => 'Preview form card',
                'button_label' => 'Preview form',
                'action' => [
                    'type' => 'form',
                    'reference_id' => $form->getKey(),
                    'display' => 'modal',
                ],
            ],
        ]), isPreview: true);

        $this->assertStringContainsString(
            route('admin.preview.pages.show', $page, absolute: false),
            $html,
        );
        $this->assertStringContainsString('Service card remains', $html);
        $this->assertStringNotContainsString('Unavailable service', $html);
        $this->assertStringContainsString('Preview form', $html);
        $this->assertStringContainsString('data-form-action-modal-url', $html);
    }

    private function legacyGrid(): array
    {
        return [
            'block_id' => self::ID,
            'section_title' => 'Legacy Grid',
            'items' => [[
                'title' => 'Legacy Feature',
                'description' => 'Legacy description',
                'button_label' => 'Legacy button',
                'button_url' => '/legacy-feature',
            ]],
        ];
    }

    private function canonicalGrid(array $items): array
    {
        return [
            'block_id' => self::ID,
            'schema_version' => 1,
            'template' => 'default',
            'content' => [
                'section_title' => 'Feature Actions',
                'items_mode' => 'static',
                'items' => $items,
            ],
            'settings' => [],
        ];
    }

    private function render(
        array $data,
        array $context = ['page_url' => '/feature-grid'],
        bool $isPreview = false,
    ): string {
        return view('partials.blocks.feature_grid', compact(
            'data',
            'context',
            'isPreview',
        ))->render();
    }

    private function form(string $displayMode, string $slug = 'feature-grid-form'): Form
    {
        return Form::query()->create([
            'name' => 'Feature Grid Form',
            'slug' => $slug,
            'status' => 'published',
            'display_mode' => $displayMode,
        ]);
    }
}
