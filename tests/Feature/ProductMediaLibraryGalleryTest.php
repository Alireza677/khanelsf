<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductMediaLibraryGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_gallery_is_synchronized_from_media_library_selection(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $first = $user->addMedia(UploadedFile::fake()->image('first.jpg'))
            ->toMediaCollection('media_library', 'public');
        $second = $user->addMedia(UploadedFile::fake()->image('second.jpg'))
            ->toMediaCollection('media_library', 'public');
        $product = Product::factory()->create();

        ProductResource::syncMediaLibraryCollection($product, 'gallery', [$first->id, $second->id]);

        $this->assertCount(2, $product->refresh()->getMedia('gallery'));
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $product->getMedia('gallery')->map(fn ($media) => $media->getCustomProperty('source_media_id'))->all(),
        );

        ProductResource::syncMediaLibraryCollection($product, 'gallery', [$second->id]);

        $this->assertCount(1, $product->refresh()->getMedia('gallery'));
        $this->assertSame($second->id, $product->getFirstMedia('gallery')->getCustomProperty('source_media_id'));
    }
}
