<?php

namespace Tests\Feature;

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

class BlockBuilderEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_and_template_use_the_translated_inspector_tabs(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ([CreatePage::class, CreateTemplate::class] as $componentClass) {
            $component = Livewire::test($componentClass)
                ->callFormComponentAction('blocks', 'add', arguments: ['block' => 'custom_html'])
                ->assertHasNoFormComponentActionErrors()
                ->assertSeeHtml('aria-label="بخش‌های تنظیمات بلوک"');

            $tabs = str($component->html())
                ->between('<nav class="block-builder-inspector__tabs"', '</nav>')
                ->toString();

            $this->assertStringContainsString('محتوا', $tabs);
            $this->assertStringContainsString('طراحی', $tabs);
            $this->assertStringContainsString('تنظیمات پیشرفته', $tabs);
            $this->assertLessThan(strpos($tabs, 'طراحی'), strpos($tabs, 'محتوا'));
            $this->assertLessThan(strpos($tabs, 'تنظیمات پیشرفته'), strpos($tabs, 'طراحی'));
        }
    }

    public function test_page_and_template_use_the_shared_two_panel_block_editor(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ($this->editors() as [$componentClass, $record]) {
            $component = Livewire::test($componentClass, ['record' => $record->getRouteKey()])
                ->assertOk()
                ->assertSee('Block Canvas')
                ->assertSee('Selected Block')
                ->assertSee('محتوا')
                ->assertSee('طراحی')
                ->assertSee('تنظیمات پیشرفته')
                ->assertSeeHtml('class="block-builder-inspector"')
                ->assertSeeHtml('class="block-builder-canvas"')
                ->assertSeeHtml('class="block-builder-card"')
                ->assertSeeHtml('data-inspector-tab="content"')
                ->assertSeeHtml('data-inspector-tab="design"')
                ->assertSeeHtml('data-inspector-tab="advanced"')
                ->assertSeeHtml('x-on:click="selectBlock(')
                ->assertSeeHtml('x-show="activeItem ===');

            $html = $component->html();
            preg_match_all('/<li[^>]+class="block-builder-card"[^>]*>.*?<\/li>/s', $html, $cards);

            $this->assertCount(2, $cards[0]);
            foreach ($cards[0] as $card) {
                $this->assertStringNotContainsString('data-field-wrapper', $card);
                $this->assertStringNotContainsString('<input', $card);
                $this->assertStringNotContainsString('<textarea', $card);
            }
        }
    }

    public function test_page_and_template_preserve_builder_actions_save_shape_and_reload(): void
    {
        $this->actingAs(User::factory()->create());

        foreach ($this->editors() as [$componentClass, $record]) {
            $component = Livewire::test($componentClass, ['record' => $record->getRouteKey()]);
            $initialKeys = array_keys($component->get('data')['blocks']);

            $component
                ->callFormComponentAction('blocks', 'add', arguments: ['block' => 'custom_html'])
                ->assertHasNoFormComponentActionErrors();

            $afterAdd = $component->get('data')['blocks'];
            $addedKey = array_key_last($afterAdd);
            $this->assertCount(3, $afterAdd);
            $this->assertSame('custom_html', $afterAdd[$addedKey]['type']);

            $component
                ->set("data.blocks.{$addedKey}.data.code", '<div>Added</div>')
                ->callFormComponentAction('blocks', 'clone', arguments: ['item' => $initialKeys[0]])
                ->assertHasNoFormComponentActionErrors();

            $afterClone = $component->get('data')['blocks'];
            $clonedKey = array_key_last($afterClone);
            $this->assertCount(4, $afterClone);
            $this->assertSame($afterClone[$initialKeys[0]], $afterClone[$clonedKey]);

            $component
                ->callFormComponentAction('blocks', 'delete', arguments: ['item' => $initialKeys[1]])
                ->assertHasNoFormComponentActionErrors();

            $remainingKeys = array_keys($component->get('data')['blocks']);
            $reorderedKeys = array_reverse($remainingKeys);

            $component
                ->callFormComponentAction('blocks', 'reorder', arguments: ['items' => $reorderedKeys])
                ->assertHasNoFormComponentActionErrors()
                ->call('save')
                ->assertHasNoFormErrors();

            $saved = $record->fresh()->blocks;

            $this->assertSame(['custom_html', 'custom_html', 'custom_html'], array_column($saved, 'type'));
            $this->assertSame(
                ['<div>First</div>', '<div>Added</div>', '<div>First</div>'],
                array_column(array_column($saved, 'data'), 'code'),
            );

            $reloaded = Livewire::test($componentClass, ['record' => $record->getRouteKey()]);
            $reloadedBlocks = array_values($reloaded->get('data')['blocks']);

            $this->assertSame(
                array_column(array_column($saved, 'data'), 'code'),
                array_column(array_column($reloadedBlocks, 'data'), 'code'),
            );
        }
    }

    public function test_registered_static_and_dynamic_blocks_render_as_canvas_cards_with_inspectors(): void
    {
        $this->actingAs(User::factory()->create());

        $pageTypes = ['hero', 'cta', 'form', 'feature_grid', 'faq', 'gallery'];
        $page = Page::factory()->create([
            'blocks' => array_map(fn (string $type): array => [
                'type' => $type,
                'data' => $type === 'hero' ? ['template' => 'default'] : [],
            ], $pageTypes),
        ]);

        $templateTypes = [
            'hero',
            'cta',
            'form',
            'feature_grid',
            'faq',
            'gallery',
            'template_archive_header',
            'template_shop_complete',
            'template_content_grid',
            'template_single_header',
            'template_single_content',
            'template_single_meta',
            'template_single_gallery',
            'template_add_to_cart',
            'custom_html',
        ];
        $template = Template::query()->create([
            'title' => 'All block types',
            'slug' => 'all-block-types',
            'type' => 'page',
            'status' => 'draft',
            'blocks' => array_map(fn (string $type): array => [
                'type' => $type,
                'data' => $type === 'hero' ? ['template' => 'default'] : [],
            ], $templateTypes),
            'conditions' => ['type' => 'all'],
        ]);

        foreach ([
            [EditPage::class, $page, $pageTypes],
            [EditTemplate::class, $template, $templateTypes],
        ] as [$componentClass, $record, $types]) {
            $component = Livewire::test($componentClass, ['record' => $record->getRouteKey()])->assertOk();
            $html = $component->html();

            preg_match_all('/class="block-builder-card"/', $html, $cards);
            $this->assertCount(count($types), $cards[0]);

            foreach ($types as $type) {
                $component->assertSeeHtml("<small>{$type}</small>");
            }
        }
    }

    /**
     * @return array<array{class-string, Page|Template}>
     */
    private function editors(): array
    {
        $blocks = [
            ['type' => 'custom_html', 'data' => ['code' => '<div>First</div>']],
            ['type' => 'custom_html', 'data' => ['code' => '<div>Second</div>']],
        ];

        return [
            [
                EditPage::class,
                Page::factory()->create([
                    'title' => 'Block editor page',
                    'slug' => 'block-editor-page-'.uniqid(),
                    'blocks' => $blocks,
                ]),
            ],
            [
                EditTemplate::class,
                Template::query()->create([
                    'title' => 'Block editor template',
                    'slug' => 'block-editor-template-'.uniqid(),
                    'type' => 'page',
                    'status' => 'draft',
                    'blocks' => $blocks,
                    'conditions' => ['type' => 'all'],
                ]),
            ],
        ];
    }
}
