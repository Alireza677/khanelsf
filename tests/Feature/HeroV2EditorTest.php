<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource\Pages\CreateTemplate;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Fixtures\HeroV2EditorLifecycleComponent;
use Tests\TestCase;

class HeroV2EditorTest extends TestCase
{
    use RefreshDatabase;

    private const ID = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cms.hero_v2_editor', true);
        config()->set('cms.hero_v2_editor_runtime', null);
    }

    public function test_flag_off_keeps_legacy_schema_and_hydration(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $schema = app(HeroBlock::class)->filamentSchema(HeroBlock::CONTEXT_PAGE);

        $this->assertSame('template', $schema[0]->getName());
        $this->assertContains('title', collect($schema)->map(fn ($field) => method_exists($field, 'getName') ? $field->getName() : null)->all());
        $this->assertNotContains('content.title', collect($schema)->map(fn ($field) => method_exists($field, 'getName') ? $field->getName() : null)->all());
    }

    public function test_all_legacy_templates_hydrate_to_unmixed_v2_state(): void
    {
        foreach (['default', 'hero_1', 'hero_2', 'hero_3', 'unknown-template'] as $template) {
            $result = app(BlockEditorHydrator::class)->hydrateV2([$this->legacyHero($template)])[0]['data'];

            $this->assertSame(2, $result['schema_version']);
            $this->assertSame($template, $result['template']);
            $this->assertSame(self::ID, $result['block_id']);
            $this->assertSame('Title', $result['content']['title']);
            $this->assertArrayNotHasKey('title', $result);
            $this->assertArrayNotHasKey('hero_1_theme', $result);
        }
    }

    public function test_nested_builder_round_trip_preserves_contract_media_repeaters_and_hidden_template_data(): void
    {
        $v2 = app(BlockEditorHydrator::class)->hydrateV2([$this->legacyHero('hero_1')]);
        $component = Livewire::test(HeroV2EditorLifecycleComponent::class, ['blocks' => $v2]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component
            ->assertSet("data.blocks.{$uuid}.data.content.media.source_id", null)
            ->set("data.blocks.{$uuid}.data.settings.background_treatment", 'animated_paths')
            ->call('save')
            ->assertHasNoFormErrors();

        $data = $component->get('saved')['blocks'][0]['data'];
        $this->assertSame(2, $data['schema_version']);
        $this->assertSame(self::ID, $data['block_id']);
        $this->assertSame('Title', $data['content']['title']);
        $this->assertSame(['One', 'Two'], array_column($data['content']['stats'], 'label'));
        $this->assertSame(['First', 'Second'], array_column($data['content']['selector']['items'], 'label'));
        $this->assertSame(['Social'], array_column($data['content']['social_links'], 'label'));
        $this->assertSame('/video.mp4', $data['content']['media']['video_url']);
        $this->assertSame('/poster.jpg', $data['content']['media']['poster_url']);
        $this->assertSame('paths', $data['settings']['background_effect']['type']);
        $this->assertArrayNotHasKey('title', $data);
    }

    public function test_template_changes_are_reactive_and_preserve_temporarily_hidden_content(): void
    {
        $v2 = app(BlockEditorHydrator::class)->hydrateV2([$this->legacyHero('hero_1')]);
        $component = Livewire::test(HeroV2EditorLifecycleComponent::class, ['blocks' => $v2]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component
            ->set("data.blocks.{$uuid}.data.content.title_secondary", 'Preserved second line')
            ->set("data.blocks.{$uuid}.data.template", 'hero_2')
            ->assertSet("data.blocks.{$uuid}.data.content.title_secondary", 'Preserved second line')
            ->set("data.blocks.{$uuid}.data.content.selector.placeholder", 'Preserved selector')
            ->set("data.blocks.{$uuid}.data.template", 'hero_1')
            ->assertSet("data.blocks.{$uuid}.data.content.title_secondary", 'Preserved second line')
            ->assertSet("data.blocks.{$uuid}.data.content.selector.placeholder", 'Preserved selector')
            ->call('save')
            ->assertHasNoFormErrors();

        $data = $component->get('saved')['blocks'][0]['data'];

        $this->assertSame('hero_1', $data['template']);
        $this->assertSame('Preserved second line', $data['content']['title_secondary']);
        $this->assertSame('Preserved selector', $data['content']['selector']['placeholder']);
        $this->assertSame(['Social'], array_column($data['content']['social_links'], 'label'));
        $this->assertSame(['First', 'Second'], array_column($data['content']['selector']['items'], 'label'));
        $this->assertSame(['One', 'Two'], array_column($data['content']['stats'], 'label'));
    }

    public function test_each_template_round_trips_to_v2_without_flat_keys(): void
    {
        foreach (['default', 'hero_1', 'hero_2', 'hero_3'] as $template) {
            $v2 = app(BlockEditorHydrator::class)->hydrateV2([$this->legacyHero($template)]);
            $component = Livewire::test(HeroV2EditorLifecycleComponent::class, ['blocks' => $v2]);
            $component->call('save')->assertHasNoFormErrors();
            $data = $component->get('saved')['blocks'][0]['data'];

            $this->assertSame($template, $data['template']);
            $this->assertSame(2, $data['schema_version']);
            $this->assertSame(self::ID, $data['block_id']);
            $this->assertSame('Title', $data['content']['title']);
            $this->assertSame('/image.jpg', $data['content']['media']['url']);
            $this->assertArrayNotHasKey('title', $data);
            $this->assertArrayNotHasKey('hero_1_theme', $data);
        }
    }

    public function test_page_and_template_editors_use_v2_hydration_without_writing_on_open(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [$this->legacyHero('hero_1')]]);
        $template = Template::query()->create([
            'title' => 'Template', 'slug' => 'v2-editor-template', 'type' => 'page', 'status' => 'draft',
            'blocks' => [$this->legacyHero('hero_2')],
        ]);
        $beforePage = $page->blocks;
        $beforeTemplate = $template->blocks;

        foreach ([
            Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]),
            Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]),
        ] as $component) {
            $data = $component->get('data')['blocks'];
            $hero = $data[array_key_first($data)]['data'];
            $this->assertSame(2, $hero['schema_version']);
            $this->assertSame('Title', $hero['content']['title']);
            $this->assertArrayNotHasKey('title', $hero);
        }

        $this->assertSame($beforePage, $page->fresh()->blocks);
        $this->assertSame($beforeTemplate, $template->fresh()->blocks);
    }

    public function test_nested_required_fields_report_nested_validation_paths(): void
    {
        $v2 = app(BlockEditorHydrator::class)->hydrateV2([$this->legacyHero('hero_2')]);
        $component = Livewire::test(HeroV2EditorLifecycleComponent::class, ['blocks' => $v2]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component
            ->set("data.blocks.{$uuid}.data.content.title", null)
            ->set("data.blocks.{$uuid}.data.content.selector.items.0.label", null)
            ->call('save')
            ->assertHasFormErrors([
                "blocks.{$uuid}.data.content.title" => 'required',
                "blocks.{$uuid}.data.content.selector.items.0.label" => 'required',
            ]);
    }

    public function test_flag_on_persists_legacy_page_and_template_as_v2_idempotently(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [$this->legacyHero('hero_1')]]);
        $template = Template::query()->create([
            'title' => 'Legacy Template', 'slug' => 'legacy-upgrade', 'type' => 'page', 'status' => 'draft',
            'blocks' => [$this->legacyHero('hero_2')],
        ]);

        foreach ([[EditPage::class, $page], [EditTemplate::class, $template]] as [$component, $record]) {
            Livewire::test($component, ['record' => $record->getRouteKey()])->call('save')->assertHasNoFormErrors();
            $once = $record->fresh()->blocks;
            $hero = $once[0]['data'];

            $this->assertSame(2, $hero['schema_version']);
            $this->assertSame(self::ID, $hero['block_id']);
            $this->assertSame('Title', $hero['content']['title']);
            $this->assertSame('/video.mp4', $hero['content']['media']['video_url']);
            $this->assertSame(['One', 'Two'], array_column($hero['content']['stats'], 'label'));
            $this->assertArrayNotHasKey('title', $hero);

            Livewire::test($component, ['record' => $record->getRouteKey()])->call('save')->assertHasNoFormErrors();
            $this->assertSame($once, $record->fresh()->blocks);
        }
    }

    public function test_v2_record_always_uses_v2_editor_and_write_when_global_flag_is_off(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $this->actingAs(User::factory()->create());
        $v2 = app(HeroDataNormalizer::class)->normalize($this->legacyHero('custom_future_template')['data']);
        $page = Page::factory()->create(['blocks' => [['type' => 'hero', 'data' => $v2]]]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $blocks = $component->get('data')['blocks'];
        $uuid = array_key_first($blocks);
        $component
            ->assertSet("data.blocks.{$uuid}.data.content.title", 'Title')
            ->set("data.blocks.{$uuid}.data.content.title", 'Edited')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame(2, $saved['schema_version']);
        $this->assertSame('custom_future_template', $saved['template']);
        $this->assertSame('Edited', $saved['content']['title']);
        $this->assertArrayNotHasKey('title', $saved);
    }

    public function test_flag_off_keeps_legacy_record_legacy_on_save(): void
    {
        config()->set('cms.hero_v2_editor', false);
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [$this->legacyHero('hero_1')]]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->call('save')->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame('Title', $saved['title']);
        $this->assertArrayNotHasKey('schema_version', $saved);
        $this->assertArrayNotHasKey('content', $saved);
    }

    public function test_create_page_and_template_persist_v2_contract(): void
    {
        $this->actingAs(User::factory()->create());
        $block = app(BlockEditorHydrator::class)->hydrateV2([$this->legacyHero('hero_1')]);

        Livewire::test(CreatePage::class)->fillForm([
            'title' => 'V2 Page', 'slug' => 'v2-page', 'template' => 'default', 'status' => 'draft', 'blocks' => $block,
        ])->call('create')->assertHasNoFormErrors();
        Livewire::test(CreateTemplate::class)->fillForm([
            'title' => 'V2 Template', 'slug' => 'v2-template-create', 'type' => 'page', 'status' => 'draft', 'blocks' => $block,
        ])->call('create')->assertHasNoFormErrors();

        foreach ([Page::query()->where('slug', 'v2-page')->firstOrFail(), Template::query()->where('slug', 'v2-template-create')->firstOrFail()] as $record) {
            $data = $record->blocks[0]['data'];
            $this->assertSame(2, $data['schema_version']);
            $this->assertSame(self::ID, $data['block_id']);
            $this->assertSame('Title', $data['content']['title']);
            $this->assertArrayNotHasKey('title', $data);
        }
    }

    public function test_failed_nested_validation_does_not_persist_conversion(): void
    {
        Log::spy();
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [$this->legacyHero('hero_2')]]);
        $before = $page->blocks;
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $uuid = array_key_first($component->get('data')['blocks']);

        $component->set("data.blocks.{$uuid}.data.content.title", null)->call('save')->assertHasFormErrors();

        $this->assertSame($before, $page->fresh()->blocks);
        Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'Hero v2 editor validation failed'
                && $context['context'] === 'page'
                && isset($context['error_fields'], $context['heroes'])
                && ! str_contains(json_encode($context), 'Description');
        });
    }

    public function test_v2_clone_duplicates_are_repaired_and_reorder_preserves_ids(): void
    {
        $this->actingAs(User::factory()->create());
        $hero = app(HeroDataNormalizer::class)->normalize($this->legacyHero('hero_1')['data']);
        $page = Page::factory()->create(['blocks' => [
            ['type' => 'hero', 'data' => $hero],
            ['type' => 'hero', 'data' => array_replace_recursive($hero, ['content' => ['title' => 'Clone']])],
        ]]);

        Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $ids = array_column(array_column($page->fresh()->blocks, 'data'), 'block_id');
        $this->assertSame(self::ID, $ids[0]);
        $this->assertCount(2, array_unique($ids));

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $component->set('data.blocks', array_reverse($component->get('data')['blocks'], true))->call('save')->assertHasNoFormErrors();

        $this->assertSame(array_reverse($ids), array_column(array_column($page->fresh()->blocks, 'data'), 'block_id'));
    }

    private function legacyHero(string $template): array
    {
        return ['type' => 'hero', 'data' => [
            'block_id' => self::ID, 'template' => $template, 'title' => 'Title', 'subtitle' => 'Lead',
            'description' => 'Description', 'image' => '/image.jpg', 'hero_2_video_url' => '/video.mp4',
            'hero_2_video_poster' => '/poster.jpg', 'hero_1_theme' => 'animated_dotted_surface',
            'selector_items' => [['label' => 'First', 'url' => '/first'], ['label' => 'Second', 'url' => '/second']],
            'stats' => [['value' => '1', 'label' => 'One'], ['value' => '2', 'label' => 'Two', 'description' => null]],
            'hero_1_social_links' => [['label' => 'Social', 'url' => '/social', 'icon' => null, 'icon_size' => null]],
        ]];
    }
}
