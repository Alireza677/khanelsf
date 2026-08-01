<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Hero\HeroBlock;
use App\Filament\Resources\TemplateResource;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Product;
use App\Models\Template;
use App\Models\User;
use Filament\Forms\Components\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class ProductTemplateEditorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCKS = [
        'product_header',
        'product_overview',
        'product_specifications',
        'product_gallery',
        'product_documents',
        'product_related',
    ];

    public function test_template_editor_exposes_product_blocks_and_their_setting_schemas(): void
    {
        $method = new ReflectionMethod(TemplateResource::class, 'blockDefinitions');
        $definitions = collect($method->invoke(null))
            ->filter(fn ($block): bool => $block instanceof Builder\Block)
            ->keyBy(fn (Builder\Block $block): string => $block->getName());

        foreach (self::BLOCKS as $key) {
            $this->assertTrue($definitions->has($key), "Template editor block [{$key}] is missing.");
        }

        $expectedSettings = [
            'product_header' => ['settings.show_price', 'settings.show_category', 'settings.show_cta'],
            'product_specifications' => ['settings.layout', 'settings.show_group'],
            'product_gallery' => ['settings.columns'],
            'product_documents' => ['settings.show_type'],
            'product_related' => ['settings.limit'],
        ];

        foreach ($expectedSettings as $key => $fields) {
            $fieldNames = collect($definitions->get($key)->getChildComponents())
                ->map(fn ($component): string => $component->getName());

            foreach ($fields as $field) {
                $this->assertTrue($fieldNames->contains($field), "Setting [{$field}] is missing from [{$key}].");
            }
        }

        $this->assertSame(self::BLOCKS, array_map(
            fn (Builder\Block $block): string => $block->getName(),
            app(BlockRegistry::class)->filamentBlocks(self::BLOCKS, HeroBlock::CONTEXT_TEMPLATE),
        ));
    }

    public function test_product_block_settings_save_and_hydrate_after_reload(): void
    {
        $this->actingAs(User::factory()->create());
        $template = $this->template('draft', [
            ['type' => 'product_header', 'data' => ['settings' => [
                'show_price' => false,
                'show_category' => true,
                'show_cta' => false,
            ]]],
            ['type' => 'product_specifications', 'data' => ['settings' => [
                'layout' => 'stacked',
                'show_group' => false,
            ]]],
            ['type' => 'product_gallery', 'data' => ['settings' => ['columns' => 4]]],
            ['type' => 'product_documents', 'data' => ['settings' => ['show_type' => false]]],
            ['type' => 'product_related', 'data' => ['settings' => ['limit' => 5]]],
        ]);

        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $state = $component->get('data')['blocks'];
        $keys = collect($state)->mapWithKeys(
            fn (array $block, string $uuid): array => [$block['type'] => $uuid],
        );

        $component
            ->assertSet("data.blocks.{$keys['product_header']}.data.settings.show_price", false)
            ->assertSet("data.blocks.{$keys['product_specifications']}.data.settings.layout", 'stacked')
            ->assertSet("data.blocks.{$keys['product_gallery']}.data.settings.columns", 4)
            ->assertSet("data.blocks.{$keys['product_documents']}.data.settings.show_type", false)
            ->assertSet("data.blocks.{$keys['product_related']}.data.settings.limit", 5)
            ->set("data.blocks.{$keys['product_header']}.data.settings.show_cta", true)
            ->set("data.blocks.{$keys['product_gallery']}.data.settings.columns", 2)
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = collect($template->fresh()->blocks)->keyBy('type');
        $this->assertTrue(data_get($saved, 'product_header.data.settings.show_cta'));
        $this->assertSame(2, data_get($saved, 'product_gallery.data.settings.columns'));
        $this->assertCount(5, $saved->pluck('data.block_id')->filter()->unique());

        $reloaded = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $reloadedState = collect($reloaded->get('data')['blocks'])->keyBy('type');

        $this->assertSame(
            $saved->pluck('data.block_id', 'type')->all(),
            $reloadedState->pluck('data.block_id', 'type')->all(),
        );
        $this->assertSame(2, data_get($reloadedState, 'product_gallery.data.settings.columns'));
        $this->assertSame('stacked', data_get($reloadedState, 'product_specifications.data.settings.layout'));
    }

    public function test_direct_product_preview_uses_the_standard_template_runtime(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->published()->create([
            'title' => 'Preview Product Runtime',
            'content' => '<p>Preview product overview</p>',
        ]);
        $product->specifications()->create([
            'group_name' => 'Technical',
            'label' => 'Thickness',
            'value' => '1.25',
            'unit' => 'mm',
        ]);
        $product->documents()->create([
            'title' => 'Preview Datasheet',
            'external_url' => 'https://example.com/preview.pdf',
            'mime_type' => 'application/pdf',
        ]);
        $related = Product::factory()->published()->create(['title' => 'Preview Related Product']);
        $product->relatedProducts()->attach($related, ['sort_order' => 1]);
        $this->template('published', [
            ['type' => 'product_header', 'data' => ['settings' => ['show_cta' => true]]],
            ['type' => 'product_overview', 'data' => []],
            ['type' => 'product_specifications', 'data' => ['settings' => ['layout' => 'stacked']]],
            ['type' => 'product_gallery', 'data' => ['settings' => ['columns' => 4]]],
            ['type' => 'product_documents', 'data' => ['settings' => ['show_type' => true]]],
            ['type' => 'product_related', 'data' => ['settings' => ['limit' => 1]]],
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.preview.products.show', $product))
            ->assertOk()
            ->assertSee('Preview Product Runtime')
            ->assertSee('Preview product overview', false)
            ->assertSee('Thickness')
            ->assertSee('Preview Datasheet')
            ->assertSee('application/pdf')
            ->assertSee('Preview Related Product')
            ->assertSee('افزودن به سبد خرید در پیش‌نمایش غیرفعال است.')
            ->assertSee('product-specifications--stacked', false);

        $this->assertStringNotContainsString(
            '<article class="project-detail product-detail">',
            $response->getContent(),
        );
    }

    public function test_template_editor_preview_renders_a_draft_product_template_with_real_context(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->published()->create([
            'title' => 'Draft Template Preview Product',
            'content' => '<p>Draft template product content</p>',
        ]);
        $template = $this->template('draft', [
            ['type' => 'product_header', 'data' => ['settings' => ['show_cta' => true]]],
            ['type' => 'product_overview', 'data' => []],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.preview.templates.show', [
                'template' => $template,
                'context_id' => $product->getKey(),
            ]))
            ->assertOk()
            ->assertSee('Draft Template Preview Product')
            ->assertSee('Draft template product content', false)
            ->assertSee('افزودن به سبد خرید در پیش‌نمایش غیرفعال است.');
    }

    public function test_legacy_product_without_foundation_data_previews_safely(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->published()->create([
            'title' => 'Legacy Product Preview',
            'content' => '<p>Legacy product body</p>',
        ]);
        $this->template('published', array_map(
            fn (string $type): array => ['type' => $type, 'data' => []],
            self::BLOCKS,
        ));

        $this->actingAs($admin)
            ->get(route('admin.preview.products.show', $product))
            ->assertOk()
            ->assertSee('Legacy Product Preview')
            ->assertSee('Legacy product body', false);
    }

    private function template(string $status, array $blocks): Template
    {
        return Template::query()->create([
            'title' => 'Product editor integration',
            'slug' => 'product-editor-integration-'.uniqid(),
            'type' => 'product_single',
            'status' => $status,
            'priority' => 0,
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => $blocks,
        ]);
    }
}
