<?php

namespace App\CMS\Templates\Recipes;

use App\CMS\Blocks\BlockEditorHydrator;
use App\CMS\Blocks\BlockIdentityManager;
use App\CMS\Templates\Recipes\Contracts\TemplateDraftStore;
use App\Models\Template;
use Illuminate\Support\Str;

final class TemplateRecipeInstantiator
{
    public function __construct(
        private readonly TemplateRecipeRegistry $recipes,
        private readonly TemplateRecipeCompatibilityValidator $compatibility,
        private readonly BlockIdentityManager $identities,
        private readonly BlockEditorHydrator $hydrator,
        private readonly TemplateDraftStore $drafts,
    ) {}

    public function makeDraft(string $recipeKey, array $attributes = []): Template
    {
        $recipe = $this->recipes->find($recipeKey);
        $this->compatibility->assertCompatible($recipe);

        $blocks = $this->identities->regenerateDocumentIds($recipe->blocks());
        $blocks = $this->hydrator->hydrate($blocks);
        $title = $this->stringOrNull($attributes['title'] ?? null)
            ?? "{$recipe->label()} v{$recipe->version()}";
        $slug = $this->stringOrNull($attributes['slug'] ?? null)
            ?? "{$recipe->key()}-v{$recipe->version()}";

        return new Template([
            'title' => $title,
            'slug' => Str::slug($slug),
            'type' => $recipe->targetType(),
            'status' => 'draft',
            'blocks' => $blocks,
            'priority' => is_numeric($attributes['priority'] ?? null) ? (int) $attributes['priority'] : 0,
            'is_default' => (bool) ($attributes['is_default'] ?? false),
            'conditions' => is_array($attributes['conditions'] ?? null)
                ? $attributes['conditions']
                : ['type' => 'all'],
        ]);
    }

    public function createDraft(string $recipeKey, array $attributes = []): Template
    {
        return $this->drafts->persist($this->makeDraft($recipeKey, $attributes));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
