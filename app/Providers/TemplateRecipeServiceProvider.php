<?php

namespace App\Providers;

use App\CMS\Templates\Recipes\Contracts\TemplateDraftStore;
use App\CMS\Templates\Recipes\EloquentTemplateDraftStore;
use App\CMS\Templates\Recipes\ProductIndustrialV1Recipe;
use App\CMS\Templates\Recipes\ProjectCaseStudyRecipe;
use App\CMS\Templates\Recipes\ServiceProfessionalV1Recipe;
use App\CMS\Templates\Recipes\TemplateRecipeRegistry;
use Illuminate\Support\ServiceProvider;

final class TemplateRecipeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TemplateRecipeRegistry::class, fn ($app): TemplateRecipeRegistry => new TemplateRecipeRegistry(
            $app,
            [
                'project_case_study' => ProjectCaseStudyRecipe::class,
                'product-industrial-v1' => ProductIndustrialV1Recipe::class,
                'service-professional-v1' => ServiceProfessionalV1Recipe::class,
            ],
        ));

        $this->app->bind(TemplateDraftStore::class, EloquentTemplateDraftStore::class);
    }
}
