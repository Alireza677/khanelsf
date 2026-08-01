<?php

namespace Tests\Unit;

use App\CMS\Templates\Recipes\Contracts\TemplateRecipe;
use App\CMS\Templates\Recipes\ProductIndustrialV1Recipe;
use App\CMS\Templates\Recipes\TemplateRecipeCompatibilityValidator;
use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\CMS\Templates\Recipes\TemplateRecipeRegistry;
use Tests\TestCase;

class ProductTemplateRecipeTest extends TestCase
{
    private const ORDER = [
        'product_header',
        'product_overview',
        'product_specifications',
        'product_gallery',
        'product_documents',
        'product_related',
        'cta',
    ];

    public function test_product_industrial_recipe_is_discoverable_with_the_requested_contract(): void
    {
        $registry = app(TemplateRecipeRegistry::class);
        $recipe = $registry->find('product-industrial-v1');

        $this->assertContains('product-industrial-v1', $registry->keys());
        $this->assertInstanceOf(ProductIndustrialV1Recipe::class, $recipe);
        $this->assertSame('product-industrial-v1', $recipe->key());
        $this->assertSame('product_single', $recipe->targetType());
        $this->assertSame(1, $recipe->version());
        $this->assertSame(self::ORDER, array_column($recipe->blocks(), 'type'));
    }

    public function test_recipe_has_canonical_schema_versions_and_valid_settings(): void
    {
        $recipe = app(ProductIndustrialV1Recipe::class);
        $blocks = $recipe->blocks();

        $this->assertSame([1, 1, 1, 1, 1, 1, 2], collect($blocks)->pluck('data.schema_version')->all());
        $this->assertSame('table', data_get($blocks, '2.data.settings.layout'));
        $this->assertSame(3, data_get($blocks, '3.data.settings.columns'));
        $this->assertTrue(data_get($blocks, '4.data.settings.show_type'));
        $this->assertSame(3, data_get($blocks, '5.data.settings.limit'));

        foreach ($blocks as $block) {
            $this->assertNull($block['data']['block_id']);
            $this->assertSame(
                ['block_id', 'schema_version', 'template', 'content', 'settings'],
                array_keys($block['data']),
            );
        }

        $validator = app(TemplateRecipeCompatibilityValidator::class);
        $this->assertSame([], $validator->validate($recipe));
        $validator->assertCompatible($recipe);
        $this->addToAssertionCount(1);
    }

    public function test_instantiation_generates_canonical_unique_identity_without_mutating_blueprint(): void
    {
        $instantiator = app(TemplateRecipeInstantiator::class);
        $first = $instantiator->makeDraft('product-industrial-v1', [
            'slug' => 'industrial-product-one',
            'is_default' => true,
            'status' => 'published',
        ]);
        $second = $instantiator->makeDraft('product-industrial-v1', [
            'slug' => 'industrial-product-two',
        ]);
        $firstIds = collect($first->blocks)->pluck('data.block_id')->all();
        $secondIds = collect($second->blocks)->pluck('data.block_id')->all();

        $this->assertFalse($first->exists);
        $this->assertSame('draft', $first->status);
        $this->assertSame('product_single', $first->type);
        $this->assertTrue($first->is_default);
        $this->assertSame(self::ORDER, collect($first->blocks)->pluck('type')->all());
        $this->assertCount(7, array_unique($firstIds));
        $this->assertCount(7, array_unique($secondIds));
        $this->assertSame([], array_values(array_intersect($firstIds, $secondIds)));

        foreach ([...$firstIds, ...$secondIds] as $id) {
            $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $id);
        }

        $this->assertTrue(collect($first->blocks)->every(
            fn (array $block): bool => array_keys($block['data'])
                === ['block_id', 'schema_version', 'template', 'content', 'settings'],
        ));
        $this->assertTrue(collect(app(ProductIndustrialV1Recipe::class)->blocks())->every(
            fn (array $block): bool => $block['data']['block_id'] === null,
        ));
    }

    public function test_compatibility_validation_rejects_noncanonical_product_settings(): void
    {
        $valid = app(ProductIndustrialV1Recipe::class);
        $recipe = new class($valid) implements TemplateRecipe
        {
            public function __construct(private readonly TemplateRecipe $valid) {}

            public function key(): string
            {
                return 'invalid-product-settings';
            }

            public function label(): string
            {
                return $this->valid->label();
            }

            public function version(): int
            {
                return $this->valid->version();
            }

            public function targetType(): string
            {
                return $this->valid->targetType();
            }

            public function description(): string
            {
                return $this->valid->description();
            }

            public function compatibility(): array
            {
                return $this->valid->compatibility();
            }

            public function blocks(): array
            {
                $blocks = $this->valid->blocks();
                $blocks[3]['data']['settings']['columns'] = 99;

                return $blocks;
            }
        };

        $errors = app(TemplateRecipeCompatibilityValidator::class)->validate($recipe);

        $this->assertTrue(collect($errors)->contains(
            fn (string $error): bool => str_contains($error, 'product_gallery')
                && str_contains($error, 'settings'),
        ));
    }
}
