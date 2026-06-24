<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(100, 999),
            'description' => fake()->sentence(),
            'status' => 'active',
            'sort_order' => 0,
            'seo_title' => null,
            'seo_description' => null,
            'seo_image' => null,
            'robots_index' => true,
            'robots_follow' => true,
        ];
    }
}
