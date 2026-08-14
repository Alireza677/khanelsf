<?php

namespace Tests\Unit;

use App\CMS\Templates\Recipes\Contracts\TemplateDraftStore;
use App\CMS\Templates\Recipes\Contracts\TemplateRecipe;
use App\CMS\Templates\Recipes\ProjectCaseStudyRecipe;
use App\CMS\Templates\Recipes\TemplateRecipeCompatibilityValidator;
use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\CMS\Templates\Recipes\TemplateRecipeRegistry;
use App\Models\Project;
use App\Models\Service;
use App\Models\Template;
use DomainException;
use Tests\TestCase;

class TemplateRecipeInfrastructureTest extends TestCase
{
    private const ORDER = [
        'project_header',
        'project_overview',
        'project_metrics',
        'project_story',
        'project_services',
        'project_gallery',
        'cta',
        'related_projects',
    ];

    public function test_project_case_study_recipe_is_registered_with_independent_version(): void
    {
        $registry = app(TemplateRecipeRegistry::class);
        $recipe = $registry->find('project_case_study');

        $this->assertSame([
            'project_case_study',
            'product-industrial-v1',
            'service-professional-v1',
        ], $registry->keys());
        $this->assertInstanceOf(ProjectCaseStudyRecipe::class, $recipe);
        $this->assertSame('project_case_study', $recipe->key());
        $this->assertSame(1, $recipe->version());
        $this->assertSame('project_single', $recipe->targetType());
        $this->assertNotEmpty($recipe->description());
        $this->assertSame(2, $recipe->blocks()[6]['data']['schema_version']);
    }

    public function test_recipe_preserves_the_approved_block_order_and_canonical_defaults(): void
    {
        $blocks = app(ProjectCaseStudyRecipe::class)->blocks();

        $this->assertSame(self::ORDER, array_column($blocks, 'type'));
        $this->assertSame('مطالعه موردی', $blocks[0]['data']['content']['eyebrow']);
        $this->assertFalse($blocks[0]['data']['settings']['show_client']);
        $this->assertSame('دستاوردهای کلیدی', $blocks[2]['data']['content']['title']);
        $this->assertTrue($blocks[3]['data']['settings']['show_results_summary']);
        $this->assertTrue($blocks[5]['data']['settings']['lightbox']);
        $this->assertSame('custom_url', data_get($blocks[6], 'data.content.primary_cta.action.type'));
        $this->assertSame('/contact', data_get($blocks[6], 'data.content.primary_cta.action.value'));
        $this->assertSame(3, $blocks[7]['data']['settings']['limit']);

        foreach ($blocks as $block) {
            $this->assertNull($block['data']['block_id']);
            $this->assertSame(
                ['block_id', 'schema_version', 'template', 'content', 'settings'],
                array_keys($block['data']),
            );
        }
    }

    public function test_instantiation_clones_payload_and_generates_unique_block_ids_per_template(): void
    {
        $instantiator = app(TemplateRecipeInstantiator::class);
        $first = $instantiator->makeDraft('project_case_study', ['slug' => 'case-study-one']);
        $second = $instantiator->makeDraft('project_case_study', ['slug' => 'case-study-two']);
        $firstIds = collect($first->blocks)->pluck('data.block_id')->all();
        $secondIds = collect($second->blocks)->pluck('data.block_id')->all();

        $this->assertCount(8, array_unique($firstIds));
        $this->assertCount(8, array_unique($secondIds));
        $this->assertSame([], array_values(array_intersect($firstIds, $secondIds)));

        foreach ([...$firstIds, ...$secondIds] as $id) {
            $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $id);
        }

        $changed = $first->blocks;
        $changed[0]['data']['content']['eyebrow'] = 'تغییر محلی';
        $first->blocks = $changed;

        $this->assertSame('مطالعه موردی', app(ProjectCaseStudyRecipe::class)->blocks()[0]['data']['content']['eyebrow']);
        $this->assertSame('مطالعه موردی', $second->blocks[0]['data']['content']['eyebrow']);
    }

    public function test_instantiation_preserves_block_schema_versions_and_creates_draft_shape(): void
    {
        $sourceBlocks = app(ProjectCaseStudyRecipe::class)->blocks();
        $template = app(TemplateRecipeInstantiator::class)->makeDraft('project_case_study', [
            'title' => 'قالب مطالعه موردی',
            'slug' => 'project-case-study',
            'priority' => 20,
            'is_default' => true,
        ]);

        $this->assertFalse($template->exists);
        $this->assertSame('قالب مطالعه موردی', $template->title);
        $this->assertSame('project-case-study', $template->slug);
        $this->assertSame('project_single', $template->type);
        $this->assertSame('draft', $template->status);
        $this->assertSame(20, $template->priority);
        $this->assertTrue($template->is_default);
        $this->assertSame(['type' => 'all'], $template->conditions);
        $this->assertSame([1, 1, 1, 1, 1, 1, 2, 1], collect($template->blocks)->pluck('data.schema_version')->all());

        foreach ($template->blocks as $position => $block) {
            $dataWithoutInstanceIdentity = $block['data'];
            $dataWithoutInstanceIdentity['block_id'] = null;

            $this->assertSame($sourceBlocks[$position]['type'], $block['type']);
            $this->assertSame($sourceBlocks[$position]['data'], $dataWithoutInstanceIdentity);
        }
    }

    public function test_create_draft_uses_store_and_cannot_publish_from_recipe_attributes(): void
    {
        $store = new class implements TemplateDraftStore
        {
            public ?Template $persisted = null;

            public function persist(Template $template): Template
            {
                $this->persisted = $template;
                $template->exists = true;

                return $template;
            }
        };
        $this->app->instance(TemplateDraftStore::class, $store);

        $template = app(TemplateRecipeInstantiator::class)->createDraft('project_case_study', [
            'title' => 'Draft from recipe',
            'slug' => 'draft-from-recipe',
            'status' => 'published',
        ]);

        $this->assertSame($template, $store->persisted);
        $this->assertTrue($template->exists);
        $this->assertSame('draft', $template->status);
        $this->assertSame('project_single', $template->type);
        $this->assertCount(8, $template->blocks);
    }

    public function test_registered_recipe_passes_compatibility_validation(): void
    {
        $validator = app(TemplateRecipeCompatibilityValidator::class);
        $recipe = app(TemplateRecipeRegistry::class)->find('project_case_study');

        $this->assertSame([], $validator->validate($recipe));
        $validator->assertCompatible($recipe);
        $this->addToAssertionCount(1);
    }

    public function test_incompatible_recipe_is_rejected_before_instantiation(): void
    {
        $recipe = new class implements TemplateRecipe
        {
            public function key(): string
            {
                return 'broken_recipe';
            }

            public function label(): string
            {
                return 'Broken';
            }

            public function version(): int
            {
                return 1;
            }

            public function targetType(): string
            {
                return 'project_single';
            }

            public function description(): string
            {
                return 'Invalid test recipe.';
            }

            public function compatibility(): array
            {
                return [
                    'blocks' => [
                        'missing_block' => [
                            'min_version' => 1,
                            'capabilities' => ['dynamic_data'],
                        ],
                    ],
                ];
            }

            public function blocks(): array
            {
                return [[
                    'type' => 'missing_block',
                    'data' => [
                        'block_id' => null,
                        'schema_version' => 1,
                        'template' => 'default',
                        'content' => [],
                        'settings' => [],
                    ],
                ]];
            }
        };
        $validator = app(TemplateRecipeCompatibilityValidator::class);

        $this->assertNotEmpty($validator->validate($recipe));
        $this->expectException(DomainException::class);
        $validator->assertCompatible($recipe);
    }

    public function test_project_services_renderer_prefers_relation_then_legacy_json_without_lazy_loading(): void
    {
        $legacyProject = new Project([
            'services' => [
                ['name' => 'خدمت Legacy اول'],
                ['name' => 'خدمت Legacy دوم'],
            ],
        ]);
        $legacyProject->setRelation('relatedServices', collect());

        $legacyHtml = $this->renderServices($legacyProject);

        $this->assertStringContainsString('خدمت Legacy اول', $legacyHtml);
        $this->assertStringContainsString('خدمت Legacy دوم', $legacyHtml);

        $relationProject = new Project([
            'services' => [['name' => 'نباید نمایش داده شود']],
        ]);
        $relationProject->setRelation('relatedServices', collect([
            new Service(['name' => 'خدمت رابطه‌ای', 'status' => 'active']),
        ]));

        $relationHtml = $this->renderServices($relationProject);

        $this->assertStringContainsString('خدمت رابطه‌ای', $relationHtml);
        $this->assertStringNotContainsString('نباید نمایش داده شود', $relationHtml);
        $this->assertTrue($relationProject->relationLoaded('relatedServices'));
    }

    private function renderServices(Project $project): string
    {
        return view('partials.blocks.project_services', [
            'data' => ['content' => ['title' => 'خدمات']],
            'context' => ['kind' => 'single', 'type' => 'project', 'model' => $project],
        ])->render();
    }
}
