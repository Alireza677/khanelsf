<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockIdentityManager;
use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\CMS\Blocks\Hero\HeroMediaResolver;
use Livewire\Livewire;
use Tests\Fixtures\BuilderFormatterLifecycleComponent;
use Tests\Fixtures\BuilderLifecycleComponent;
use Tests\TestCase;

class FilamentBuilderLifecycleTest extends TestCase
{
    public function test_builder_format_state_using_runs_too_late_and_replaces_uuid_hydration_hook(): void
    {
        $component = Livewire::test(BuilderFormatterLifecycleComponent::class, [
            'blocks' => [['type' => 'hero', 'data' => ['title' => 'Legacy title']]],
        ]);
        $blocks = $component->get('data')['blocks'];

        $this->assertSame(0, array_key_first($blocks));
        $this->assertNull($blocks[0]['data']['content']['title']);
        $this->assertSame('Legacy title', $blocks[0]['data']['title']);
    }

    public function test_nested_text_fields_hydrate_edit_and_dehydrate_without_flattening(): void
    {
        $component = $this->nestedComponent();
        $uuid = $this->firstUuid($component);

        $component
            ->assertSet("data.blocks.{$uuid}.data.content.title", 'First')
            ->assertSet("data.blocks.{$uuid}.data.settings.alignment", 'start')
            ->set("data.blocks.{$uuid}.data.content.title", 'Edited')
            ->call('save');

        $saved = $component->get('saved')['blocks'][0]['data'];
        $this->assertSame('Edited', $saved['content']['title']);
        $this->assertSame('start', $saved['settings']['alignment']);
        $this->assertArrayNotHasKey('content.title', $saved);
    }

    public function test_nested_repeaters_preserve_order_optional_values_and_shape(): void
    {
        $component = $this->nestedComponent();
        $uuid = $this->firstUuid($component);
        $component->call('save');
        $content = $component->get('saved')['blocks'][0]['data']['content'];

        $this->assertSame(['One', 'Two'], array_column($content['stats'], 'label'));
        $this->assertNull($content['stats'][0]['description']);
        $this->assertSame(24, $content['stats'][0]['icon_size']);
        $this->assertSame('/first', $content['selector']['items'][0]['url']);
        $this->assertSame('/social', $content['social_links'][0]['url']);
    }

    public function test_nested_view_field_has_correct_state_path_without_corrupting_siblings(): void
    {
        $component = $this->nestedComponent();
        $uuid = $this->firstUuid($component);
        $path = "data.blocks.{$uuid}.data.content.media.url";

        $component
            ->assertSeeHtml($path)
            ->set($path, '/changed.jpg')
            ->assertSet("data.blocks.{$uuid}.data.content.title", 'First')
            ->call('save');

        $data = $component->get('saved')['blocks'][0]['data'];
        $this->assertSame('/changed.jpg', $data['content']['media']['url']);
        $this->assertSame('First', $data['content']['title']);
    }

    public function test_multiple_blocks_keep_uuid_and_nested_state_isolation(): void
    {
        $component = Livewire::test(BuilderLifecycleComponent::class, ['blocks' => [
            $this->hero('A'),
            $this->hero('B'),
        ]]);
        $keys = array_keys($component->get('data')['blocks']);

        $this->assertCount(2, array_unique($keys));
        $this->assertIsString($keys[0]);
        $this->assertIsString($keys[1]);

        $component
            ->set("data.blocks.{$keys[0]}.data.content.title", 'Changed A')
            ->assertSet("data.blocks.{$keys[1]}.data.content.title", 'B');
    }

    public function test_skip_render_callback_works_with_nested_state_path(): void
    {
        $component = $this->nestedComponent();
        $uuid = $this->firstUuid($component);

        $component
            ->set("data.blocks.{$uuid}.data.settings.background_treatment", 'paths')
            ->assertSet('nestedUpdateHandled', true)
            ->assertSet("data.blocks.{$uuid}.data.settings.background_treatment", 'paths');
    }

    public function test_future_v2_hydrator_output_round_trips_through_nested_builder_fixture(): void
    {
        $resolver = new class extends HeroMediaResolver
        {
            public function resolveSourceId(?string $url): ?int
            {
                return null;
            }
        };
        $hydrator = new BlockEditorHydrator(new BlockIdentityManager, new HeroDataNormalizer($resolver));
        $blocks = $hydrator->hydrateV2([['type' => 'hero', 'data' => [
            'template' => 'hero_1', 'title' => 'Title', 'subtitle' => 'Lead', 'description' => 'Description',
            'image' => '/hero.jpg', 'primary_button_label' => 'Primary', 'primary_button_url' => '/primary',
            'stats' => [['value' => '1', 'label' => 'One']], 'hero_1_height' => 560,
            'hero_1_theme' => 'animated_paths', 'paths_speed' => 'slow',
        ]]]);
        $component = Livewire::test(BuilderLifecycleComponent::class, ['blocks' => $blocks]);
        $component->call('save');
        $data = $component->get('saved')['blocks'][0]['data'];

        $this->assertSame('Title', $data['content']['title']);
        $this->assertSame('Lead', $data['content']['lead']);
        $this->assertSame('Description', $data['content']['description']);
        $this->assertSame('/hero.jpg', $data['content']['media']['url']);
        $this->assertSame('Primary', $data['content']['primary_cta']['label']);
        $this->assertSame([['value' => '1', 'label' => 'One', 'description' => null, 'icon' => null, 'icon_size' => null]], $data['content']['stats']);
        $this->assertSame(560, $data['settings']['height']['desktop']);
        $this->assertSame('paths', $data['settings']['background_effect']['type']);
        $this->assertSame('slow', $data['settings']['background_effect']['speed']);
    }

    private function nestedComponent()
    {
        return Livewire::test(BuilderLifecycleComponent::class, ['blocks' => [$this->hero('First')]]);
    }

    private function firstUuid($component): string
    {
        $key = array_key_first($component->get('data')['blocks']);
        $this->assertIsString($key);

        return $key;
    }

    private function hero(string $title): array
    {
        return ['type' => 'hero', 'data' => [
            'content' => [
                'title' => $title,
                'media' => ['url' => '/hero.jpg'],
                'stats' => [
                    ['value' => '1', 'label' => 'One', 'description' => null, 'icon' => 'icon-one', 'icon_size' => 24],
                    ['value' => '2', 'label' => 'Two', 'description' => 'Optional', 'icon' => null, 'icon_size' => null],
                ],
                'selector' => ['items' => [['label' => 'First', 'url' => '/first']]],
                'social_links' => [['label' => 'Social', 'url' => '/social']],
            ],
            'settings' => [
                'alignment' => 'start',
                'background_treatment' => 'image',
                'height' => ['desktop' => 500],
            ],
        ]];
    }
}
