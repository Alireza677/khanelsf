<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopFilterPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_query_count_does_not_grow_per_product_or_per_shared_media_reference(): void
    {
        Storage::fake('public');
        $category = ProductCategory::factory()->create();
        $owner = User::factory()->admin()->create();
        $media = $owner->addMedia(UploadedFile::fake()->image('shared.jpg'))->toMediaCollection('media_library', 'public');
        $attribute = ProductAttribute::create(['name' => 'Material', 'is_filterable' => true]);
        $value = $attribute->values()->create(['name' => 'Steel']);
        $create = function (int $number) use ($category, $media, $attribute, $value): void {
            foreach (range(1, $number) as $index) {
                $product = Product::factory()->published()->create(['product_category_id' => $category->id]);
                ProductResource::syncFeaturedImage($product, $media->id);
                app(ProductCatalogService::class)->syncAttributes($product, [['attribute_id' => $attribute->id, 'value_ids' => [$value->id]]]);
            }
        };
        $create(4);
        $this->get(route('shop.index'))->assertOk(); // Warm shared settings and framework caches.
        DB::enableQueryLog();
        DB::flushQueryLog();
        $first = $this->get(route('shop.index'))->assertOk();
        $firstTotal = count(DB::getQueryLog());
        $catalogQueries = fn (array $queries) => array_filter($queries, fn ($entry) => ! preg_match('/from ["`]settings["`]/i', $entry['query']));
        // SettingsService deliberately disables its request cache under PHPUnit.
        // Measure catalog/media/facet growth independently of those uncached settings reads.
        $firstCount = count($catalogQueries(DB::getQueryLog()));
        DB::disableQueryLog();
        $create(8);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $second = $this->get(route('shop.index'))->assertOk();
        $secondTotal = count(DB::getQueryLog());
        $secondCount = count($catalogQueries(DB::getQueryLog()));
        DB::disableQueryLog();
        $this->assertCount(4, $first->viewData('products')->items());
        $this->assertCount(12, $second->viewData('products')->items());
        $this->assertLessThanOrEqual($firstCount + 1, $secondCount);
        foreach ($second->viewData('products') as $product) {
            $this->assertTrue($product->relationLoaded('mediaUsages'));
            $this->assertTrue($product->mediaUsages->first()->relationLoaded('media'));
        }
        fwrite(STDERR, "\nShop fixture non-settings queries: 4 products={$firstCount}; 12 products={$secondCount}; total queries with uncached test settings={$firstTotal}/{$secondTotal}\n");
    }
}
