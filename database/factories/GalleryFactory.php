<?php

namespace Database\Factories;

use App\Models\GalleryCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GalleryFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'gallery_category_id' => GalleryCategory::factory(),
            'project_id' => null,
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->sentence(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'type' => 'image',
            'status' => 'published',
            'published_at' => now(),
            'is_featured' => false,
            'sort_order' => 0,
            'robots_index' => true,
            'robots_follow' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => 'draft',
            'published_at' => null,
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => [
            'is_featured' => true,
        ]);
    }

    public function noindex(): static
    {
        return $this->state(fn (): array => [
            'robots_index' => false,
        ]);
    }
}
