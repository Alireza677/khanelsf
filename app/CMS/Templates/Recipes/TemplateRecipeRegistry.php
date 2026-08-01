<?php

namespace App\CMS\Templates\Recipes;

use App\CMS\Templates\Recipes\Contracts\TemplateRecipe;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

final class TemplateRecipeRegistry
{
    /**
     * @param  array<string, class-string<TemplateRecipe>>  $recipesByKey
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $recipesByKey,
    ) {}

    public function find(string $key): TemplateRecipe
    {
        $class = $this->recipesByKey[$key] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Template recipe [{$key}] is not registered.");
        }

        $recipe = $this->container->make($class);

        if ($recipe->key() !== $key) {
            throw new InvalidArgumentException("Registered recipe key [{$key}] does not match recipe key [{$recipe->key()}].");
        }

        return $recipe;
    }

    /** @return array<string> */
    public function keys(): array
    {
        return array_keys($this->recipesByKey);
    }

    /** @return array<string, TemplateRecipe> */
    public function all(): array
    {
        return collect($this->keys())
            ->mapWithKeys(fn (string $key): array => [$key => $this->find($key)])
            ->all();
    }
}
