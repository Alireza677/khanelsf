<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'product_category_id' => ProductCategory::factory(),
            'title' => Str::title($title),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(100, 999),
            'excerpt' => fake()->sentence(),
            'content' => '<p>'.fake()->paragraph().'</p>',
            'price' => fake()->numberBetween(25, 500),
            'sale_price' => null,
            'sku' => strtoupper(fake()->bothify('SKU-###')),
            'status' => 'draft',
            'published_at' => null,
            'is_featured' => false,
            'sort_order' => 0,
            'has_stock' => true,
            'stock_status' => 'in_stock',
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
