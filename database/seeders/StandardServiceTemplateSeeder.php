<?php

namespace Database\Seeders;

use App\CMS\Blocks\BlockRegistry;
use App\CMS\Blocks\Contracts\BlockNormalizer;
use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Templates\Recipes\TemplateRecipeCompatibilityValidator;
use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\CMS\Templates\Recipes\TemplateRecipeRegistry;
use App\CMS\Templates\TemplatePublicationValidator;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class StandardServiceTemplateSeeder extends Seeder
{
    public const RECIPE_KEY = 'service-professional-v1';

    public const TEMPLATE_SLUG = 'service-standard-fa-v1';

    public function run(): void
    {
        $recipe = app(TemplateRecipeRegistry::class)->find(self::RECIPE_KEY);
        app(TemplateRecipeCompatibilityValidator::class)->assertCompatible($recipe);

        DB::transaction(function (): void {
            $template = Template::query()
                ->where('slug', self::TEMPLATE_SLUG)
                ->lockForUpdate()
                ->first();

            if ($template && $template->type !== 'service_single') {
                throw new RuntimeException('The standard service template slug is already used by another template type.');
            }

            if (! $template) {
                $template = app(TemplateRecipeInstantiator::class)->makeDraft(self::RECIPE_KEY, [
                    'title' => 'قالب استاندارد جزئیات خدمت',
                    'slug' => self::TEMPLATE_SLUG,
                    'priority' => 0,
                    'is_default' => true,
                    'conditions' => ['type' => 'all'],
                ]);
            } elseif (! $template->hasBlocks()) {
                $draft = app(TemplateRecipeInstantiator::class)->makeDraft(self::RECIPE_KEY);
                $template->blocks = $draft->blocks;
            } else {
                $draft = app(TemplateRecipeInstantiator::class)->makeDraft(self::RECIPE_KEY);
                $template->blocks = $this->syncRecipePresentation($template->blocks, $draft->blocks);
            }

            $template->status = 'published';
            $template->is_default = true;
            $template->conditions = ['type' => 'all'];

            $errors = app(TemplatePublicationValidator::class)->validate($template->toArray());
            if ($errors !== []) {
                throw new RuntimeException('The standard service template is not publishable: '.implode(' ', $errors));
            }

            Template::query()
                ->where('type', 'service_single')
                ->where('is_default', true)
                ->when($template->exists, fn ($query) => $query->whereKeyNot($template->getKey()))
                ->update(['is_default' => false]);

            $template->save();
        }, 3);
    }

    /**
     * Refresh canonical presentation settings without overwriting editor-owned
     * content, actions, block identities, or extra blocks.
     */
    private function syncRecipePresentation(array $current, array $recipe): array
    {
        $recipeByType = collect($recipe)->keyBy('type');
        $presentTypes = collect($current)->pluck('type')->filter()->all();

        $updated = collect($current)->map(function (array $block) use ($recipeByType): array {
            $canonical = $recipeByType->get($block['type'] ?? null);

            if (! is_array($canonical)) {
                return $block;
            }

            $currentSettings = is_array(data_get($block, 'data.settings'))
                ? data_get($block, 'data.settings')
                : [];
            $canonicalSettings = is_array(data_get($canonical, 'data.settings'))
                ? data_get($canonical, 'data.settings')
                : [];

            // Header actions are editor-owned once a template exists. Fresh
            // installs still receive the portable defaults from the recipe.
            if (($block['type'] ?? null) === 'service_header') {
                unset($canonicalSettings['primary_action'], $canonicalSettings['secondary_action']);
            }

            $block['data']['settings'] = array_replace_recursive(
                $currentSettings,
                $canonicalSettings,
            );

            return $block;
        });

        $merged = $updated
            ->concat(collect($recipe)->reject(fn (array $block): bool => in_array($block['type'] ?? null, $presentTypes, true)))
            ->values()
            ->all();

        // array_replace_recursive() intentionally preserves editor-owned data,
        // but it also preserves legacy key order and shapes. Run the merged
        // data through the exact normalizers used by the publication validator.
        // Do not use BlockEditorHydrator here: its identity manager may replace
        // existing IDs, while a normalizer preserves block_id by contract.
        $normalizers = [
            ...app(BlockRegistry::class)->normalizers(),
            'cta' => app(CTADataNormalizer::class),
        ];

        return collect($merged)->map(function (array $block) use ($normalizers): array {
            $normalizer = $normalizers[$block['type'] ?? ''] ?? null;

            if (! $normalizer instanceof BlockNormalizer) {
                return $block;
            }

            $block['data'] = $normalizer->normalize(
                is_array($block['data'] ?? null) ? $block['data'] : [],
            );

            return $block;
        })->all();
    }
}
