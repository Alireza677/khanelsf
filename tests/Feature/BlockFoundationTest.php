<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\CTA\CTABlock;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Blocks\FeatureGrid\FeatureGridBlock;
use App\CMS\Blocks\FeatureGrid\FeatureGridDataNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Filament\Resources\TemplateResource;
use App\Models\Page;
use App\Models\User;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

class BlockFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_resolves_the_explicit_hero_definition_and_metadata(): void
    {
        $registry = app(BlockRegistry::class);
        $hero = $registry->find('hero');

        $this->assertInstanceOf(HeroBlock::class, $hero);
        $this->assertSame([
            'hero',
            'cta',
            'form',
            'feature_grid',
            'site_header',
            'project_header',
            'project_overview',
            'project_metrics',
            'project_services',
            'project_gallery',
            'project_story',
            'related_projects',
            'product_header',
            'product_overview',
            'product_specifications',
            'product_gallery',
            'product_documents',
            'product_related',
            'service_header',
            'service_overview',
            'service_benefits',
            'service_process',
            'service_deliverables',
            'service_projects',
            'service_gallery',
            'related_services',
        ], $registry->keys());
        $cta = $registry->find('cta');
        $this->assertInstanceOf(CTABlock::class, $cta);
        $this->assertSame(2, $cta->version());
        $featureGrid = $registry->find('feature_grid');
        $this->assertInstanceOf(FeatureGridBlock::class, $featureGrid);
        $this->assertSame(1, $featureGrid->version());
        $this->assertInstanceOf(BlockNormalizer::class, app(HeroDataNormalizer::class));
        $this->assertInstanceOf(BlockNormalizer::class, app(CTADataNormalizer::class));
        $this->assertInstanceOf(BlockNormalizer::class, app(FeatureGridDataNormalizer::class));
        $this->assertSame('hero', $hero->key());
        $this->assertSame(2, $hero->version());
        $this->assertSame('default', $hero->defaultTemplate());
        $this->assertSame(
            ['default', 'hero_1', 'hero_2', 'hero_3'],
            array_keys($hero->templates()),
        );
        $this->assertSame(
            ['hero'],
            array_map(
                fn (Builder\Block $block): string => $block->getName(),
                $registry->filamentBlocks(['hero'], HeroBlock::CONTEXT_PAGE),
            ),
        );
    }

    public function test_page_builder_keeps_the_current_hero_block_schema_and_data_shape(): void
    {
        $this->actingAs(User::factory()->create());
        $legacyData = [
            'template' => 'hero_1',
            'hero_1_theme' => 'image',
            'title' => 'Existing hero',
            'primary_button_label' => 'Continue',
            'primary_button_url' => '/continue',
            'image' => 'https://example.com/hero.jpg',
        ];
        $page = Page::factory()->create([
            'blocks' => [['type' => 'hero', 'data' => $legacyData]],
        ]);

        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $builder = collect($component->instance()->form->getFlatComponents(withHidden: true))
            ->first(fn (Component $field): bool => $field instanceof Builder && $field->getName() === 'blocks');

        $this->assertInstanceOf(Builder::class, $builder);
        $hero = $builder->getBlock('hero');
        $this->assertNotNull($hero);
        $this->assertSame('هیرو', $hero->getLabel());
        $this->assertHeroFields($hero->getChildComponents());

        $template = collect($hero->getChildComponents())
            ->first(fn (Component $field): bool => $field instanceof Select && $field->getName() === 'template');
        $this->assertSame(
            ['default', 'hero_1', 'hero_2', 'hero_3'],
            array_keys($template->getOptions()),
        );
        $this->assertHeroDefaults($hero->getChildComponents());

        $uuid = array_key_first($component->get('data')['blocks']);
        $hydratedData = $component->get("data.blocks.{$uuid}.data");

        foreach ($legacyData as $key => $value) {
            $this->assertSame($value, $hydratedData[$key]);
        }

        $this->assertArrayNotHasKey('id', $hydratedData);
        $this->assertArrayNotHasKey('version', $hydratedData);
        $this->assertArrayNotHasKey('content', $hydratedData);
        $this->assertArrayNotHasKey('settings', $hydratedData);
    }

    public function test_template_builder_keeps_the_current_hero_block_schema(): void
    {
        $method = new ReflectionMethod(TemplateResource::class, 'blockDefinitions');
        $blocks = $method->invoke(null);
        $hero = collect($blocks)->first(fn (Builder\Block $block): bool => $block->getName() === 'hero');

        $this->assertNotNull($hero);
        $this->assertHeroFields($hero->getChildComponents());

        $template = collect($hero->getChildComponents())
            ->first(fn (Component $field): bool => $field instanceof Select && $field->getName() === 'template');
        $this->assertSame(
            ['default', 'hero_1', 'hero_2', 'hero_3'],
            array_keys($template->getOptions()),
        );
        $this->assertHeroDefaults($hero->getChildComponents());
    }

    /** @param array<Component> $components */
    private function assertHeroFields(array $components): void
    {
        $names = collect($this->flattenComponents($components))
            ->filter(fn (Component $component): bool => $component instanceof Field)
            ->map(fn (Field $field): string => $field->getName())
            ->all();

        foreach ([
            'template',
            'title',
            'subtitle',
            'description',
            'primary_button_label',
            'primary_button_url',
            'secondary_button_label',
            'secondary_button_url',
            'hero_1_theme',
            'hero_2_alignment',
            'hero_3_alignment',
            'image',
            'stats',
        ] as $name) {
            $this->assertContains($name, $names, "Hero field [{$name}] is missing.");
        }
    }

    /**
     * @param  array<Component>  $components
     * @return array<Component>
     */
    private function flattenComponents(array $components): array
    {
        $flat = [];

        foreach ($components as $component) {
            $flat[] = $component;

            if (method_exists($component, 'getChildComponents')) {
                array_push($flat, ...$this->flattenComponents($component->getChildComponents()));
            }
        }

        return $flat;
    }

    /** @param array<Component> $components */
    private function assertHeroDefaults(array $components): void
    {
        $fields = collect($this->flattenComponents($components))
            ->filter(fn (Component $component): bool => $component instanceof Field)
            ->keyBy(fn (Field $field): string => $field->getName());

        $this->assertSame('default', $fields['template']->getDefaultState());
        $this->assertSame('image', $fields['hero_1_theme']->getDefaultState());
        $this->assertSame('left', $fields['hero_2_alignment']->getDefaultState());
        $this->assertSame('right', $fields['hero_3_alignment']->getDefaultState());
        $this->assertTrue($fields['title']->isRequired());
    }
}
