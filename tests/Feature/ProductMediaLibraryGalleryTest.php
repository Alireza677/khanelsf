<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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

        $this->assertCount(2, $product->refresh()->galleryImages());
        $this->assertSame([$first->id, $second->id], $product->galleryImages()->pluck('id')->all());
        $this->assertCount(2, $product->mediaUsages);
        $this->assertCount(2, Media::all());

        ProductResource::syncMediaLibraryCollection($product, 'gallery', [$second->id]);

        $this->assertCount(1, $product->refresh()->galleryImages());
        $this->assertSame($second->id, $product->galleryImages()->first()->id);
        $this->assertDatabaseHas('media', ['id' => $first->id]);
        $this->assertDatabaseHas('media', ['id' => $second->id]);
    }
}
