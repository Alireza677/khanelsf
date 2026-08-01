<?php

namespace App\CMS\Templates\Recipes;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Templates\Recipes\Contracts\TemplateRecipe;
use App\Models\Template;
use DomainException;

final class TemplateRecipeCompatibilityValidator
{
    public function __construct(
        private readonly BlockRegistry $blocks,
        private readonly CTADataNormalizer $ctas,
    ) {}

    /** @return array<string> */
    public function validate(TemplateRecipe $recipe): array
    {
        $errors = [];

        if (preg_match('/^[a-z][a-z0-9_-]*$/', $recipe->key()) !== 1) {
            $errors[] = "Recipe key [{$recipe->key()}] must use a lowercase identifier.";
        }

        if ($recipe->version() < 1) {
            $errors[] = "Recipe [{$recipe->key()}] must have a positive version.";
        }

        if (! array_key_exists($recipe->targetType(), Template::TYPES)) {
            $errors[] = "Recipe target [{$recipe->targetType()}] is not a supported template type.";
        }

        $requirements = $recipe->compatibility()['blocks'] ?? [];
        $recipeBlockTypes = [];

        foreach ($recipe->blocks() as $position => $block) {
            $type = $block['type'] ?? null;
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if (! is_string($type) || $type === '') {
                $errors[] = "Recipe block at position [{$position}] has no valid type.";

                continue;
            }

            $recipeBlockTypes[] = $type;

            if (! $this->blocks->has($type)) {
                $errors[] = "Recipe block [{$type}] is not registered.";

                continue;
            }

            $definition = $this->blocks->find($type);
            $schemaVersion = $data['schema_version'] ?? null;
            $expectedContextCapability = $this->contextCapabilityFor($recipe->targetType());
            $entityContextCapabilities = array_intersect(
                $definition->capabilities(),
                ['project_context', 'product_context', 'service_context'],
            );

            if ($entityContextCapabilities !== []
                && ! in_array($expectedContextCapability, $entityContextCapabilities, true)) {
                $errors[] = "Recipe block [{$type}] is not compatible with target [{$recipe->targetType()}].";
            }

            if (! is_int($schemaVersion) || $schemaVersion !== $definition->version()) {
                $errors[] = "Recipe block [{$type}] schema version must equal installed version [{$definition->version()}].";
            }

            $template = $data['template'] ?? null;

            if (! is_string($template) || ! array_key_exists($template, $definition->templates())) {
                $errors[] = "Recipe block [{$type}] uses an unsupported block template.";
            }

            if (($data['block_id'] ?? null) !== null) {
                $errors[] = "Recipe block [{$type}] must not persist a block_id.";
            }

            $normalizer = $definition instanceof BlockNormalizer
                ? $definition
                : ($type === 'cta' ? $this->ctas : null);

            if ($normalizer
                && ($normalizer->normalize($data)['settings'] ?? []) !== ($data['settings'] ?? [])) {
                $errors[] = "Recipe block [{$type}] settings are not canonical for the installed schema.";
            }
        }

        foreach ($requirements as $type => $requirement) {
            if (! in_array($type, $recipeBlockTypes, true)) {
                $errors[] = "Required block [{$type}] is missing from recipe composition.";
            }

            if (! $this->blocks->has($type)) {
                $errors[] = "Required block [{$type}] is not registered.";

                continue;
            }

            $definition = $this->blocks->find($type);
            $minimumVersion = (int) ($requirement['min_version'] ?? 1);

            if ($definition->version() < $minimumVersion) {
                $errors[] = "Block [{$type}] requires version [{$minimumVersion}] or newer.";
            }

            foreach ($requirement['capabilities'] ?? [] as $capability) {
                if (! in_array($capability, $definition->capabilities(), true)) {
                    $errors[] = "Block [{$type}] is missing capability [{$capability}].";
                }
            }
        }

        return array_values(array_unique($errors));
    }

    public function assertCompatible(TemplateRecipe $recipe): void
    {
        $errors = $this->validate($recipe);

        if ($errors !== []) {
            throw new DomainException(implode(' ', $errors));
        }
    }

    private function contextCapabilityFor(string $targetType): ?string
    {
        return match ($targetType) {
            'project_single' => 'project_context',
            'product_single' => 'product_context',
            'service_single' => 'service_context',
            default => null,
        };
    }
}
