<?php

namespace Database\Factories;

use App\Models\ProjectCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProjectFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'project_category_id' => ProjectCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'excerpt' => fake()->sentence(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'client_name' => fake()->company(),
            'location' => fake()->city(),
            'project_date' => now()->subMonth()->toDateString(),
            'services' => [['name' => 'Strategy']],
            'attributes' => [['label' => 'Timeline', 'value' => '4 weeks']],
            'external_url' => null,
            'status' => 'draft',
            'published_at' => null,
            'is_featured' => false,
            'sort_order' => 0,
            'seo_title' => null,
            'seo_description' => null,
            'seo_image' => null,
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
