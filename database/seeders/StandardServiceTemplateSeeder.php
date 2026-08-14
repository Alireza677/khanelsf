<?php

namespace Database\Seeders;

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
}
