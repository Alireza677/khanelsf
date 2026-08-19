<?php

namespace Tests\Unit;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Templates\Recipes\ServiceProfessionalV1Recipe;
use App\CMS\Templates\Recipes\TemplateRecipeCompatibilityValidator;
use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\CMS\Templates\Recipes\TemplateRecipeRegistry;
use App\CMS\Templates\TemplatePublicationValidator;
use Tests\TestCase;

class ServiceTemplateRecipeTest extends TestCase
{
    private const ORDER = [
        'service_header',
        'service_overview',
        'service_benefits',
        'service_process',
        'service_deliverables',
        'service_projects',
        'service_gallery',
        'related_services',
        'cta',
    ];

    public function test_service_recipe_is_registered_with_stable_metadata_and_order(): void
    {
        $registry = app(TemplateRecipeRegistry::class);
        $recipe = $registry->find('service-professional-v1');

        $this->assertContains('service-professional-v1', $registry->keys());
        $this->assertInstanceOf(ServiceProfessionalV1Recipe::class, $recipe);
        $this->assertSame('service-professional-v1', $recipe->key());
        $this->assertSame('صفحه حرفه‌ای خدمت', $recipe->label());
        $this->assertSame(3, $recipe->version());
        $this->assertSame('service_single', $recipe->targetType());
        $this->assertSame(self::ORDER, array_column($recipe->blocks(), 'type'));
        $this->assertNotContains('form', array_column($recipe->blocks(), 'type'));
        $blocks = collect($recipe->blocks())->keyBy('type');
        $this->assertSame('modern-split', data_get($blocks['service_header'], 'data.settings.variant'));
        $this->assertSame('end', data_get($blocks['service_header'], 'data.settings.image_position'));
        $this->assertSame('شروع همکاری', data_get($blocks['service_header'], 'data.settings.primary_action.label'));
        $this->assertSame('custom_url', data_get($blocks['service_header'], 'data.settings.primary_action.action.type'));
        $this->assertSame('#', data_get($blocks['service_header'], 'data.settings.primary_action.action.value'));
        $this->assertSame('مشاوره و گفتگو', data_get($blocks['service_header'], 'data.settings.secondary_action.label'));
        $this->assertSame('professional', data_get($blocks['service_overview'], 'data.settings.variant'));
        $this->assertSame('icon-cards', data_get($blocks['service_benefits'], 'data.settings.variant'));
        $this->assertSame('connected-steps', data_get($blocks['service_process'], 'data.settings.variant'));
        $this->assertSame('compact-grid', data_get($blocks['service_deliverables'], 'data.settings.variant'));
        $this->assertSame('visual-cards', data_get($blocks['service_projects'], 'data.settings.variant'));
        $this->assertSame('horizontal-gallery', data_get($blocks['service_gallery'], 'data.settings.variant'));
    }

    public function test_recipe_blocks_are_canonical_domain_free_and_compatible(): void
    {
        $recipe = app(ServiceProfessionalV1Recipe::class);

        foreach ($recipe->blocks() as $block) {
            $this->assertSame(
                ['block_id', 'schema_version', 'template', 'content', 'settings'],
                array_keys($block['data']),
            );
            $this->assertNull($block['data']['block_id']);
            $this->assertArrayNotHasKey('service', $block['data']['content']);
            $this->assertArrayNotHasKey('name', $block['data']['content']);
            $this->assertArrayNotHasKey('overview', $block['data']['content']);
            $this->assertArrayNotHasKey('benefits', $block['data']['content']);
            $this->assertArrayNotHasKey('process', $block['data']['content']);
            $this->assertArrayNotHasKey('deliverables', $block['data']['content']);
        }

        $cta = collect($recipe->blocks())->firstWhere('type', 'cta');
        $this->assertNull(data_get($cta, 'data.content.title'));
        $this->assertNull(data_get($cta, 'data.content.primary_cta.action.form_id'));
        $this->assertNull(data_get($cta, 'data.content.primary_cta.action.url'));

        $validator = app(TemplateRecipeCompatibilityValidator::class);
        $this->assertSame([], $validator->validate($recipe));
        $validator->assertCompatible($recipe);
        $this->addToAssertionCount(1);
    }

    public function test_instantiation_is_draft_non_default_idempotent_and_generates_unique_ids(): void
    {
        $instantiator = app(TemplateRecipeInstantiator::class);
        $first = $instantiator->makeDraft('service-professional-v1', [
            'title' => 'Service Professional v1',
            'slug' => 'service-professional-v1',
            'status' => 'published',
        ]);
        $second = $instantiator->makeDraft('service-professional-v1', [
            'slug' => 'service-professional-v1-second',
        ]);
        $firstIds = collect($first->blocks)->pluck('data.block_id')->all();
        $secondIds = collect($second->blocks)->pluck('data.block_id')->all();

        $this->assertFalse($first->exists);
        $this->assertSame('Service Professional v1', $first->title);
        $this->assertSame('service-professional-v1', $first->slug);
        $this->assertSame('service_single', $first->type);
        $this->assertSame('draft', $first->status);
        $this->assertFalse($first->is_default);
        $this->assertSame(['type' => 'all'], $first->conditions);
        $this->assertCount(9, array_unique($firstIds));
        $this->assertCount(9, array_unique($secondIds));
        $this->assertSame([], array_values(array_intersect($firstIds, $secondIds)));
        $this->assertTrue(collect(app(ServiceProfessionalV1Recipe::class)->blocks())->every(
            fn (array $block): bool => $block['data']['block_id'] === null,
        ));
    }

    public function test_publication_validator_accepts_recipe_draft_and_rejects_wrong_target_and_duplicate_ids(): void
    {
        $template = app(TemplateRecipeInstantiator::class)->makeDraft('service-professional-v1', [
            'slug' => 'valid-service-publication',
        ]);
        $attributes = $template->toArray();
        $validator = app(TemplatePublicationValidator::class);

        $this->assertSame([], $validator->validate($attributes));

        $form = app(BlockRegistry::class)->find('form');
        $this->assertInstanceOf(BlockNormalizer::class, $form);
        $formData = $form->normalize([
            'block_id' => '01JFORMBLOCK00000000000000',
            'content' => ['form_id' => 1],
        ]);
        $attributes['blocks'][] = ['type' => 'form', 'data' => $formData];
        $this->assertSame([], $validator->validate($attributes));

        $attributes['blocks'][0]['type'] = 'product_header';
        $errors = $validator->validate($attributes);
        $this->assertTrue(collect($errors)->contains(
            fn (string $error): bool => str_contains($error, 'product_header')
                && str_contains($error, 'not compatible'),
        ));

        $attributes = $template->toArray();
        $attributes['blocks'][1]['data']['block_id'] = $attributes['blocks'][0]['data']['block_id'];
        $errors = $validator->validate($attributes);
        $this->assertTrue(collect($errors)->contains(
            fn (string $error): bool => str_contains($error, 'must be unique'),
        ));
    }
}
