<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\LsfProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LsfProductSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_ten_persian_lsf_products_without_duplicates(): void
    {
        $this->seed(LsfProductSeeder::class);
        $this->seed(LsfProductSeeder::class);

        $products = Product::query()->orderBy('sort_order')->get();

        $this->assertCount(10, $products);
        $this->assertCount(4, ProductCategory::query()->get());

        foreach ($products as $product) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $product->slug);
            $this->assertNull($product->sku);
            $this->assertDoesNotMatchRegularExpression(
                '/[a-z]/i',
                implode(' ', [
                    $product->title,
                    $product->excerpt,
                    strip_tags($product->content),
                    $product->seo_title,
                    $product->seo_description,
                ]),
            );
        }

        ProductCategory::query()->each(function (ProductCategory $category): void {
            $this->assertDoesNotMatchRegularExpression(
                '/[a-z]/i',
                implode(' ', [
                    $category->name,
                    $category->slug,
                    $category->description,
                    $category->seo_title,
                    $category->seo_description,
                ]),
            );
        });
    }
}
