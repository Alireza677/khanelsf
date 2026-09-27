<?php

namespace Tests\Feature;

use App\Filament\Forms\Components\BlockBuilder;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TemplateBlockPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_target_changes_filter_new_blocks_and_preserve_existing_blocks(): void
    {
        $this->actingAs(User::factory()->create());
        $template = Template::query()->create([
            'title' => 'Picker test',
            'slug' => 'picker-test',
            'type' => 'product_single',
            'status' => 'draft',
            'blocks' => [
                ['type' => 'template_add_to_cart', 'data' => ['button_label' => 'Existing button']],
                ['type' => 'product_overview', 'data' => []],
            ],
        ]);

        $editor = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])
            ->assertOk();
        $builder = fn (): BlockBuilder => collect($editor->instance()->form->getFlatComponents())
            ->first(fn ($component): bool => $component instanceof BlockBuilder);
        $picker = fn (): array => array_map(fn ($block): string => $block->getName(), $builder()->getBlockPickerBlocks());

        $this->assertContains('template_add_to_cart', $picker());
        $this->assertNotContains('template_content_grid', $picker());
        $this->assertNotContains('service_header', $picker());

        $editor->set('data.type', 'blog_index');
        $this->assertContains('template_content_grid', $picker());
        $this->assertContains('cta', $picker());
        $this->assertNotContains('template_add_to_cart', $picker());
        $this->assertNotContains('product_overview', $picker());
        $this->assertTrue($builder()->hasBlock('template_add_to_cart'));
        $this->assertTrue($builder()->hasBlock('product_overview'));
        $this->assertCount(2, $builder()->getChildComponentContainers());

        $state = $editor->get('data.blocks');
        foreach (['add', 'addBetween'] as $action) {
            $editor->callFormComponentAction('blocks', $action, arguments: [
                'block' => 'template_add_to_cart',
                'afterItem' => array_key_first($state),
            ]);
            $this->assertSame($state, $editor->get('data.blocks'));
        }

        $editor->assertFormComponentActionHidden('blocks', 'clone', arguments: ['item' => array_key_first($state)])
            ->call('mountFormComponentAction', $builder()->getKey(), 'clone', ['item' => array_key_first($state)]);
        $this->assertSame($state, $editor->get('data.blocks'));

        $editor->callFormComponentAction('blocks', 'add', arguments: ['block' => 'custom_html']);
        $addedKey = array_key_last($editor->get('data.blocks'));
        $editor->set("data.blocks.{$addedKey}.data.code", '<p>New general block</p>')
            ->call('save')->assertHasNoFormErrors();

        $saved = $template->fresh();
        $this->assertSame('blog_index', $saved->type);
        $this->assertSame(['template_add_to_cart', 'product_overview', 'custom_html'], array_column($saved->blocks, 'type'));
        $this->assertSame('Existing button', $saved->blocks[0]['data']['button_label']);

        $reloaded = Livewire::test(EditTemplate::class, ['record' => $saved->getRouteKey()])->assertOk();
        $this->assertSame('Existing button', array_values($reloaded->get('data.blocks'))[0]['data']['button_label']);
    }
}
