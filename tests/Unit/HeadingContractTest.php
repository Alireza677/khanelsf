<?php

namespace Tests\Unit;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Blocks\Hero\HeroBlock;
use App\CMS\Blocks\Hero\HeroDataNormalizer;
use App\CMS\Blocks\Support\HeadingLevel;
use App\CMS\Blocks\Support\PageHeadingAudit;
use App\Filament\Resources\Concerns\WarnsAboutMultiplePageHeadings;
use App\Filament\Resources\PageResource\Pages\CreatePage;
use App\Filament\Resources\PageResource\Pages\EditPage;
use Tests\TestCase;

class HeadingContractTest extends TestCase
{
    public function test_heading_levels_are_limited_to_h1_h2_and_h3(): void
    {
        $field = HeadingLevel::field();

        $this->assertSame(['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'], $field->getOptions());
        $this->assertFalse($field->isRequired());
        $this->assertSame('h3', HeadingLevel::normalize('h3'));
        $this->assertSame('h2', HeadingLevel::normalize('h4'));
        $this->assertSame('h1', HeadingLevel::normalize(null, 'h1'));
    }

    public function test_all_registered_title_blocks_normalize_a_backward_compatible_heading_default(): void
    {
        $expected = [
            'form' => 'h2',
            'feature_grid' => 'h2',
            'project_header' => 'h1',
            'project_overview' => 'h2',
            'project_metrics' => 'h2',
            'project_services' => 'h2',
            'project_gallery' => 'h2',
            'project_story' => 'h2',
            'related_projects' => 'h2',
            'product_header' => 'h1',
            'product_overview' => 'h2',
            'product_specifications' => 'h2',
            'product_gallery' => 'h2',
            'product_documents' => 'h2',
            'product_related' => 'h2',
            'service_header' => 'h1',
            'service_overview' => 'h2',
            'service_benefits' => 'h2',
            'service_process' => 'h2',
            'service_deliverables' => 'h2',
            'service_projects' => 'h2',
            'service_gallery' => 'h2',
            'related_services' => 'h2',
        ];
        $registry = app(BlockRegistry::class);

        foreach ([HeroDataNormalizer::class, CTADataNormalizer::class] as $normalizer) {
            $this->assertSame('h2', data_get(app($normalizer)->normalize([]), 'settings.heading_tag'));
            $this->assertSame('h3', data_get(app($normalizer)->normalize([
                'settings' => ['heading_tag' => 'h3'],
                'schema_version' => 2,
            ]), 'settings.heading_tag'));
        }

        foreach ($expected as $key => $default) {
            $definition = $registry->find($key);

            $this->assertSame($default, data_get($definition->normalize([]), 'settings.heading_tag'), $key);
            $this->assertSame('h3', data_get($definition->normalize([
                'settings' => ['heading_tag' => 'h3'],
            ]), 'settings.heading_tag'), $key);
        }
    }

    public function test_shared_heading_partial_renders_h3_and_rejects_unsupported_tags(): void
    {
        $this->assertStringContainsString(
            '<h3 class="block-title">Section</h3>',
            view('partials.blocks._heading', ['title' => 'Section', 'tag' => 'h3'])->render(),
        );
        $this->assertStringContainsString(
            '<h2 class="block-title">Section</h2>',
            view('partials.blocks._heading', ['title' => 'Section', 'tag' => 'h6'])->render(),
        );
    }

    public function test_every_registered_title_block_exposes_the_shared_heading_schema(): void
    {
        $registry = app(BlockRegistry::class);
        $pageBlocks = ['hero', 'cta', 'form', 'feature_grid'];
        $titleBlocks = [
            ...$pageBlocks,
            'project_header', 'project_overview', 'project_metrics', 'project_services',
            'project_gallery', 'project_story', 'related_projects',
            'product_header', 'product_overview', 'product_specifications', 'product_gallery',
            'product_documents', 'product_related',
            'service_header', 'service_overview', 'service_benefits', 'service_process',
            'service_deliverables', 'service_projects', 'service_gallery', 'related_services',
        ];

        foreach ($titleBlocks as $key) {
            $context = in_array($key, $pageBlocks, true)
                ? HeroBlock::CONTEXT_PAGE
                : HeroBlock::CONTEXT_TEMPLATE;
            $field = collect($registry->find($key)->filamentSchema($context))
                ->first(fn ($component): bool => method_exists($component, 'getName')
                    && in_array($component->getName(), ['heading_tag', 'settings.heading_tag'], true));

            $this->assertNotNull($field, $key);
            $this->assertSame(['h1' => 'H1', 'h2' => 'H2', 'h3' => 'H3'], $field->getOptions(), $key);
        }
    }

    public function test_page_heading_audit_warns_only_for_multiple_visible_h1_blocks(): void
    {
        $audit = app(PageHeadingAudit::class);
        $blocks = [
            ['type' => 'hero', 'data' => ['content' => ['title' => 'Primary'], 'settings' => ['heading_tag' => 'h1']]],
            ['type' => 'cta', 'data' => ['content' => ['title' => 'Secondary'], 'settings' => ['heading_tag' => 'h1']]],
            ['type' => 'feature_grid', 'data' => ['content' => ['section_title' => ''], 'settings' => ['heading_tag' => 'h1']]],
            ['type' => 'stats_section', 'data' => ['section_title' => 'Stats', 'heading_tag' => 'h2']],
        ];

        $this->assertTrue($audit->hasMultipleH1($blocks));
        $this->assertCount(2, $audit->h1Blocks($blocks));
        $this->assertContains(WarnsAboutMultiplePageHeadings::class, class_uses_recursive(CreatePage::class));
        $this->assertContains(WarnsAboutMultiplePageHeadings::class, class_uses_recursive(EditPage::class));
    }
}
