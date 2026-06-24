<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => Str::title($title),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'blocks' => null,
            'template' => 'default',
            'status' => 'draft',
            'published_at' => null,
            'seo_title' => null,
            'seo_description' => null,
            'seo_image' => null,
            'seo_keywords' => null,
            'robots_index' => true,
            'robots_follow' => true,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }
}
