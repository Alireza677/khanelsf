<?php

namespace Tests\Feature;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Project\ProjectGalleryBlock;
use App\CMS\Blocks\Project\ProjectHeaderBlock;
use App\CMS\Blocks\Project\ProjectMetricsBlock;
use App\CMS\Blocks\Project\ProjectOverviewBlock;
use App\CMS\Blocks\Project\ProjectServicesBlock;
use App\CMS\Blocks\Project\ProjectStoryBlock;
use App\CMS\Blocks\Project\RelatedProjectsBlock;
use App\Filament\Resources\TemplateResource;
use Filament\Forms\Components\Builder;
use ReflectionMethod;
use Tests\TestCase;

class ProjectBlocksFoundationTest extends TestCase
{
    private const KEYS = [
        'project_header',
        'project_overview',
        'project_metrics',
        'project_services',
        'project_gallery',
        'project_story',
        'related_projects',
    ];

    public function test_project_blocks_are_registered_with_contracts_and_templates(): void
    {
        $registry = app(BlockRegistry::class);

        $this->assertSame(self::KEYS, array_values(array_intersect($registry->keys(), self::KEYS)));

        foreach (self::KEYS as $key) {
            $block = $registry->find($key);

            $this->assertInstanceOf(BlockNormalizer::class, $block);
            $this->assertSame(1, $block->version());
            $this->assertSame('default', $block->defaultTemplate());
            $this->assertSame("partials.blocks.{$key}", $block->templates()['default']->view);
            $this->assertContains('project_context', $block->capabilities());
        }

        $this->assertInstanceOf(ProjectOverviewBlock::class, $registry->find('project_overview'));
        $this->assertInstanceOf(ProjectHeaderBlock::class, $registry->find('project_header'));
        $this->assertInstanceOf(ProjectMetricsBlock::class, $registry->find('project_metrics'));
        $this->assertInstanceOf(ProjectServicesBlock::class, $registry->find('project_services'));
        $this->assertInstanceOf(ProjectGalleryBlock::class, $registry->find('project_gallery'));
        $this->assertInstanceOf(ProjectStoryBlock::class, $registry->find('project_story'));
        $this->assertInstanceOf(RelatedProjectsBlock::class, $registry->find('related_projects'));
        $this->assertSame(
            'partials.blocks.project_overview',
            $registry->renderView('project_overview', ['template' => 'default']),
        );
        $this->assertSame(
            'partials.blocks.project_overview',
            $registry->renderView('project_overview', ['template' => 'unknown']),
        );
        $this->assertSame('partials.blocks.hero', $registry->renderView('hero', ['template' => 'hero_1']));
        $this->assertNull($registry->renderView('legacy_unregistered_block'));
    }

    public function test_normalizers_are_canonical_idempotent_and_ignore_flat_legacy_keys(): void
    {
        $registry = app(BlockRegistry::class);

        foreach (self::KEYS as $key) {
            /** @var BlockNormalizer $normalizer */
            $normalizer = $registry->find($key);
            $once = $normalizer->normalize([
                'title' => 'Legacy title must not be imported',
                'content' => ['title' => 'Canonical title'],
                'settings' => ['columns' => 99, 'limit' => 99],
                'unknown' => 'discard me',
            ]);

            $this->assertSame(
                ['block_id', 'schema_version', 'template', 'content', 'settings'],
                array_keys($once),
            );
            if ($key === 'project_header') {
                $this->assertSame(['eyebrow' => null], $once['content']);
            } else {
                $this->assertSame('Canonical title', $once['content']['title']);
            }
            $this->assertArrayNotHasKey('title', $once);
            $this->assertArrayNotHasKey('unknown', $once);
            $this->assertSame($once, $normalizer->normalize($once));
        }
    }

    public function test_template_editor_exposes_all_project_blocks_through_registry_schemas(): void
    {
        $method = new ReflectionMethod(TemplateResource::class, 'blockDefinitions');
        $names = collect($method->invoke(null))
            ->filter(fn ($block): bool => $block instanceof Builder\Block)
            ->map(fn (Builder\Block $block): string => $block->getName());

        foreach (self::KEYS as $key) {
            $this->assertTrue($names->contains($key), "Template editor block [{$key}] is missing.");
        }

        $blocks = app(BlockRegistry::class)->filamentBlocks(self::KEYS, HeroBlock::CONTEXT_TEMPLATE);
        $this->assertSame(self::KEYS, array_map(
            fn (Builder\Block $block): string => $block->getName(),
            $blocks,
        ));

        $dynamicMethod = new ReflectionMethod(TemplateResource::class, 'usesDynamicBlocks');

        foreach (self::KEYS as $key) {
            $this->assertTrue($dynamicMethod->invoke(null, [['type' => $key, 'data' => []]]));
        }
    }

    public function test_editor_hydration_applies_registered_project_normalizer_and_identity(): void
    {
        $result = app(BlockEditorHydrator::class)->hydrate([[
            'type' => 'project_metrics',
            'data' => [
                'content' => ['title' => 'Outcomes'],
                'settings' => [],
                'stale_key' => 'remove',
            ],
        ]]);

        $data = $result[0]['data'];

        $this->assertNotEmpty($data['block_id']);
        $this->assertSame(1, $data['schema_version']);
        $this->assertSame('default', $data['template']);
        $this->assertSame(['title' => 'Outcomes'], $data['content']);
        $this->assertSame(['heading_tag' => 'h2'], $data['settings']);
        $this->assertArrayNotHasKey('stale_key', $data);
        $this->assertSame($result, app(BlockEditorHydrator::class)->hydrate($result));
    }
}
