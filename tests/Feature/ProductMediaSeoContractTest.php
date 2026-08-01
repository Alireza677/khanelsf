<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\ProductMediaService;
use App\Services\ProductTemplateContextBuilder;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaSeoContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_schema_uses_effective_price_and_irt_currency(): void
    {
        $product = Product::factory()->published()->create([
            'price' => 150000,
            'sale_price' => 125000,
        ]);

        $schema = app(SeoService::class)->forProduct($product)->schema;

        $this->assertSame('125000.00', $schema['offers']['price']);
        $this->assertSame('IRT', $schema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $schema['offers']['availability']);
    }

    public function test_product_schema_contains_featured_and_gallery_images(): void
    {
        Storage::fake('public');

        $product = Product::factory()->published()->create([
            'seo_image' => '/legacy/seo-product.jpg',
        ]);
        $featured = $product
            ->addMedia(UploadedFile::fake()->image('featured.jpg'))
            ->toMediaCollection('featured_image', 'public');
        $firstGallery = $product
            ->addMedia(UploadedFile::fake()->image('gallery-one.jpg'))
            ->toMediaCollection('gallery', 'public');
        $secondGallery = $product
            ->addMedia(UploadedFile::fake()->image('gallery-two.jpg'))
            ->toMediaCollection('gallery', 'public');

        $seo = app(SeoService::class)->forProduct($product->refresh());
        $absoluteUrl = fn (string $url): string => str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://')
                ? $url
                : url($url);

        $this->assertSame(url('/legacy/seo-product.jpg'), $seo->ogImage);
        $this->assertSame([
            url('/legacy/seo-product.jpg'),
            $absoluteUrl($featured->getUrl()),
            $absoluteUrl($firstGallery->getUrl()),
            $absoluteUrl($secondGallery->getUrl()),
        ], $seo->schema['image']);
    }

    public function test_template_context_exposes_the_product_media_contract(): void
    {
        Storage::fake('public');

        $product = Product::factory()->published()->create();
        $featured = $product
            ->addMedia(UploadedFile::fake()->image('featured.jpg'))
            ->toMediaCollection('featured_image', 'public');
        $gallery = $product
            ->addMedia(UploadedFile::fake()->image('gallery.jpg'))
            ->toMediaCollection('gallery', 'public');
        $document = $product->documents()->create([
            'title' => 'Installation guide',
            'external_url' => 'https://example.com/installation.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $context = app(ProductTemplateContextBuilder::class)->build($product->refresh());
        $media = $context['media'];

        $this->assertSame(['featured', 'gallery', 'documents'], array_keys($media));
        $this->assertSame($featured->id, $media['featured']['id']);
        $this->assertSame($featured->getUrl(), $media['featured']['url']);
        $this->assertSame([$gallery->getUrl()], $media['gallery']->pluck('url')->all());
        $this->assertSame($document->id, $media['documents']->firstOrFail()['id']);
        $this->assertSame('https://example.com/installation.pdf', $media['documents']->firstOrFail()['url']);
    }

    public function test_legacy_product_without_foundation_media_has_a_safe_contract(): void
    {
        $product = Product::factory()->published()->create([
            'seo_image' => '/legacy/product.jpg',
        ]);

        $media = app(ProductMediaService::class)->context($product);
        $seo = app(SeoService::class)->forProduct($product);

        $this->assertNull($media['featured']);
        $this->assertTrue($media['gallery']->isEmpty());
        $this->assertTrue($media['documents']->isEmpty());
        $this->assertSame([url('/legacy/product.jpg')], $seo->schema['image']);
        $this->assertSame(url('/legacy/product.jpg'), $seo->ogImage);
    }
}
