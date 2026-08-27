<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ServiceResource;
use App\Models\Page;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class SharedMediaAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_master_asset_can_be_referenced_by_multiple_models_without_copying(): void
    {
        Storage::fake('public');

        $assetKey = (string) Str::ulid();
        $asset = User::factory()->create()
            ->addMedia(UploadedFile::fake()->image('canonical.jpg'))
            ->usingFileName($assetKey.'.jpg')
            ->withProperties([
                'asset_key' => $assetKey,
                'original_filename' => 'canonical.jpg',
                'checksum_sha256' => str_repeat('a', 64),
            ])
            ->toMediaCollection('media_library', 'public');
        $canonicalUrl = $asset->getUrl();
        $firstPage = Page::factory()->create();
        $secondPage = Page::factory()->create();
        $service = Service::query()->create(['name' => 'Shared service', 'slug' => 'shared-service']);
        $project = Project::factory()->create();

        PageResource::syncFeaturedImage($firstPage, $asset->id);
        PageResource::syncFeaturedImage($secondPage, $asset->id);
        ServiceResource::syncFeaturedImage($service, $asset->id);
        ProjectResource::syncMediaLibraryCollection($project, 'gallery', [$asset->id]);

        $this->assertCount(1, Media::all());
        $this->assertCount(1, Storage::disk('public')->allFiles());
        $this->assertDatabaseCount('media_usages', 4);
        $this->assertSame($canonicalUrl, $firstPage->refresh()->featuredImageUrl());
        $this->assertSame($canonicalUrl, $secondPage->refresh()->featuredImageUrl());
        $this->assertSame($canonicalUrl, $service->refresh()->featuredImageUrl());
        $this->assertSame($canonicalUrl, $project->refresh()->galleryImages()->first()->getUrl());

        PageResource::syncFeaturedImage($firstPage, null);

        $this->assertNull($firstPage->refresh()->featuredImage());
        $this->assertSame($canonicalUrl, $secondPage->refresh()->featuredImageUrl());
        $this->assertSame($canonicalUrl, $service->refresh()->featuredImageUrl());
        $this->assertSame($canonicalUrl, $project->refresh()->galleryImages()->first()->getUrl());
        $this->assertCount(1, Media::all());
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_master_asset_with_usages_cannot_be_silently_deleted(): void
    {
        Storage::fake('public');

        $asset = User::factory()->create()
            ->addMedia(UploadedFile::fake()->image('protected.jpg'))
            ->toMediaCollection('media_library', 'public');
        $page = Page::factory()->create();
        PageResource::syncFeaturedImage($page, $asset->id);

        try {
            $asset->delete();
            $this->fail('Deleting an in-use master asset should be restricted.');
        } catch (QueryException) {
            $this->assertDatabaseHas('media', ['id' => $asset->id]);
            $this->assertNotNull($page->refresh()->featuredImage());
        }
    }
}
