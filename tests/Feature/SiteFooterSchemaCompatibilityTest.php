<?php

namespace Tests\Feature;

use App\CMS\Blocks\SiteFooter\SiteFooterDataNormalizer;
use App\CMS\Blocks\SiteFooter\SiteFooterRuntime;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Setting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SiteFooterSchemaCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_v1_and_normalized_data_keep_the_same_runtime_and_html_without_writing_the_template(): void
    {
        $input = $this->fixture('legacy-v1');
        foreach ($input['content']['menu_columns'] as $column) {
            $menu = Menu::query()->forceCreate([
                'id' => $column['menu_id'], 'title' => $column['title'],
                'slug' => 'footer-'.$column['menu_id'], 'status' => 'active',
            ]);
            MenuItem::query()->create([
                'menu_id' => $menu->getKey(), 'title' => $column['title'].' قدیمی',
                'type' => MenuItem::TYPE_CUSTOM_URL, 'url' => '/link-'.$menu->getKey(),
                'status' => 'active', 'sort_order' => 1,
            ]);
        }
        foreach (['site_name' => 'سایت آزمایشی', 'contact_phone' => '021 12345678', 'working_hours' => '۸ تا ۱۷'] as $key => $value) {
            Setting::query()->create(['key' => $key, 'value' => $value, 'group' => 'general', 'type' => 'text']);
        }
        $template = Template::query()->create([
            'title' => 'Legacy', 'slug' => 'legacy-footer', 'type' => 'site_footer', 'status' => 'draft',
            'blocks' => [['type' => 'site_footer', 'data' => $input]],
        ]);
        $before = $template->getRawOriginal('blocks');
        $normalizer = app(SiteFooterDataNormalizer::class);
        $normalized = $normalizer->normalize($input);
        $runtime = app(SiteFooterRuntime::class);

        $this->assertSame($runtime->prepare($input), $runtime->prepare($normalized));
        $html = view('partials.blocks.site-footer-corporate-dark', ['data' => $input])->render();
        $this->assertSame($html, view('partials.blocks.site-footer-corporate-dark', ['data' => $normalized])->render());
        $this->assertStringContainsString('معرفی اختصاصی قدیمی', $html);
        $this->assertStringContainsString('tel:02112345678', $html);
        $this->assertStringContainsString('href="/privacy"', $html);
        $this->assertStringContainsString('/link-101', $html);
        $this->assertStringNotContainsString('نشان غیرفعال', $html);
        $this->assertSame($before, $template->fresh()->getRawOriginal('blocks'));
    }

    public function test_opening_a_legacy_template_only_upgrades_editor_state_and_explicit_save_keeps_legacy_layout(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $data = $this->editorData('legacy-empty-v1');
        $template = $this->template($data);
        $before = $template->getRawOriginal('blocks');
        $editor = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->assertOk();
        $blocks = array_values($editor->get('data.blocks'));

        $this->assertSame($before, $template->fresh()->getRawOriginal('blocks'));
        $this->assertSame(2, $blocks[0]['data']['schema_version']);
        $this->assertNull($blocks[0]['data']['content']['composition']);
        $this->assertSame($data['block_id'], $blocks[0]['data']['block_id']);
        $editor->call('save')->assertHasNoFormErrors();
        $saved = $template->fresh()->blocks[0]['data'];
        $this->assertSame(app(SiteFooterDataNormalizer::class)->normalize($data), $saved);
        Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame($saved, $template->fresh()->blocks[0]['data']);
    }

    public function test_composition_controls_preserve_identity_order_settings_and_out_of_scope_data_after_save_reload(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $data = $this->editorData('composition-v2');
        $template = $this->template($data);
        $editor = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->assertOk();
        $key = array_key_first($editor->get('data.blocks'));
        $composition = $data['content']['composition'];
        $composition['main']['columns'] = array_reverse($composition['main']['columns']);
        $composition['main']['columns'][0]['elements'][0]['enabled'] = false;
        $settings = ['container' => 'narrow', 'spacing' => 'compact', 'grid' => ['desktop' => 5, 'tablet' => 3, 'mobile' => 1]];
        $editor->set("data.blocks.{$key}.data.content.composition", $composition)
            ->set("data.blocks.{$key}.data.settings", $settings)
            ->call('save')->assertHasNoFormErrors();

        $saved = $template->fresh()->blocks[0]['data'];
        foreach ($composition['main']['columns'] as &$column) {
            foreach ($column['elements'] as &$element) {
                if ($element['type'] === 'contact') {
                    // Optional empty controls express inheritance, never a snapshot of Settings.
                    $element['content']['overrides'] += array_fill_keys(['phone', 'mobile', 'email', 'address', 'working_hours'], null);
                }
            }
            unset($element);
        }
        unset($column);
        $this->assertSame($composition, $saved['content']['composition']);
        $this->assertSame($settings, $saved['settings']);
        $this->assertSame($data['block_id'], $saved['block_id']);
        Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame($saved, $template->fresh()->blocks[0]['data']);
    }

    public function test_unknown_version_and_element_fail_save_without_changing_the_stored_document(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $template = $this->template($this->editorData('composition-v2'));
        $before = $template->getRawOriginal('blocks');

        foreach (['schema_version' => 99, 'content.composition.main.columns.0.elements.0.type' => 'future_element'] as $path => $value) {
            $editor = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
            $key = array_key_first($editor->get('data.blocks'));
            $statePath = $path;
            if ($path !== 'schema_version') {
                $columns = $editor->get("data.blocks.{$key}.data.content.composition.main.columns");
                $columnKey = array_key_first($columns);
                $elementKey = array_key_first($columns[$columnKey]['elements']);
                $statePath = "content.composition.main.columns.{$columnKey}.elements.{$elementKey}.type";
            }
            $editor->set("data.blocks.{$key}.data.{$statePath}", $value)->call('save')->assertHasErrors();
            $this->assertSame($before, $template->fresh()->getRawOriginal('blocks'));
        }
    }

    public function test_explicit_conversion_add_clone_move_disable_save_reload_and_return_to_legacy(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $template = $this->template($this->editorData('legacy-empty-v1'));
        $before = $template->getRawOriginal('blocks');
        $editor = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->assertOk();
        $blockKey = array_key_first($editor->get('data.blocks'));
        $root = "blocks.{$blockKey}.data";
        $columnsPath = "{$root}.content.composition.main.columns";
        $editor->callFormComponentAction("{$root}.convertFooterAction", 'convertFooter', ['mode' => 'empty']);
        $this->assertSame($before, $template->fresh()->getRawOriginal('blocks'));
        $editor->callFormComponentAction($columnsPath, 'add');
        $columns = $editor->get('data.'.$columnsPath);
        $first = array_key_first($columns);
        $elementsPath = "{$columnsPath}.{$first}.elements";
        $editor->callFormComponentAction($elementsPath, 'add', ['element_type' => 'branding']);
        $elements = $editor->get('data.'.$elementsPath);
        $elementKey = array_key_first($elements);
        $originalId = $elements[$elementKey]['id'];
        $editor->set("data.{$elementsPath}.{$elementKey}.content.source", 'manual')
            ->set("data.{$elementsPath}.{$elementKey}.content.name", 'نام اختصاصی فوتر')
            ->set("data.{$elementsPath}.{$elementKey}.content.source", 'site');
        $editor->callFormComponentAction($columnsPath, 'clone', arguments: ['item' => $first]);
        $columns = $editor->get('data.'.$columnsPath);
        $second = array_key_last($columns);
        $this->assertNotSame($columns[$first]['id'], $columns[$second]['id']);
        $copiedId = array_values($columns[$second]['elements'])[0]['id'];
        $this->assertNotSame($originalId, $copiedId);
        $editor->callFormComponentAction($elementsPath, 'moveToColumn', ['column_id' => $columns[$second]['id']], ['item' => $elementKey]);
        $columns = $editor->get('data.'.$columnsPath);
        $this->assertSame([], $columns[$first]['elements']);
        $this->assertSame([$copiedId, $originalId], array_column($columns[$second]['elements'], 'id'));
        $movedKey = array_key_last($columns[$second]['elements']);
        $editor->set("data.{$columnsPath}.{$second}.elements.{$movedKey}.enabled", false)
            ->callFormComponentAction("{$columnsPath}.{$second}.elements", 'moveUp', arguments: ['item' => $movedKey])
            ->call('save')->assertHasNoFormErrors();
        $saved = $template->fresh()->blocks[0]['data'];
        $this->assertSame([$originalId, $copiedId], array_column($saved['content']['composition']['main']['columns'][1]['elements'], 'id'));
        $this->assertFalse($saved['content']['composition']['main']['columns'][1]['elements'][0]['enabled']);
        $this->assertSame('نام اختصاصی فوتر', $saved['content']['composition']['main']['columns'][1]['elements'][0]['content']['name']);
        $this->assertSame('site', $saved['content']['composition']['main']['columns'][1]['elements'][0]['content']['source']);
        $reload = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertSame($saved, $template->fresh()->blocks[0]['data']);
        $reloadKey = array_key_first($reload->get('data.blocks'));
        $reload->callFormComponentAction("blocks.{$reloadKey}.data.returnToLegacyAction", 'returnToLegacy')
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull($template->fresh()->blocks[0]['data']['content']['composition']);
        $this->assertSame([], $template->fresh()->blocks[0]['data']['content']['menu_columns']);
    }

    private function editorData(string $fixture): array
    {
        $data = $this->fixture($fixture);
        if ($fixture === 'composition-v2') {
            // The real ActionPicker validates referenced pages, unlike phase 1's Hidden payload.
            foreach ([30, 31] as $id) {
                Page::factory()->published()->create(['id' => $id]);
            }
        }

        return $data;
    }

    private function template(array $data): Template
    {
        return Template::query()->create([
            'title' => 'Footer schema', 'slug' => 'footer-schema', 'type' => 'site_footer', 'status' => 'draft',
            'blocks' => [['type' => 'site_footer', 'data' => $data]],
        ]);
    }

    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/site-footer/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
