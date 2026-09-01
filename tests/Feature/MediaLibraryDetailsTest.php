<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaResource;
use App\Filament\Resources\MediaResource\Pages\ListMedia;
use App\Filament\Resources\MediaResource\Pages\UploadMedia;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MediaLibraryDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_upload_has_immutable_canonical_identity_and_filename(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(UploadMedia::class)
            ->set('data.files', [UploadedFile::fake()->image('IMG_random-name.jpg', 640, 480)])
            ->call('save');

        $asset = Media::query()->sole();
        $this->assertTrue(Str::isUlid($asset->asset_key));
        $this->assertSame('IMG_random-name.jpg', $asset->original_filename);
        $this->assertSame($asset->asset_key.'.jpg', $asset->file_name);
        $this->assertSame(64, strlen($asset->checksum_sha256));
        $this->assertStringContainsString('/storage/media/'.$asset->asset_key.'/', $asset->getUrl());
        $this->assertStringNotContainsString('IMG_random-name', $asset->getUrl());
        $this->assertSame(['media/'.$asset->asset_key.'/'.$asset->file_name], Storage::disk('public')->allFiles());
    }

    public function test_metadata_edit_updates_the_canonical_asset_without_creating_a_file_or_row(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $assetKey = (string) Str::ulid();
        $asset = $admin->addMedia(UploadedFile::fake()->image('canonical.jpg', 640, 480))
            ->usingFileName($assetKey.'.jpg')
            ->withProperties([
                'asset_key' => $assetKey,
                'original_filename' => 'canonical.jpg',
                'checksum_sha256' => str_repeat('a', 64),
            ])
            ->toMediaCollection('media_library', 'public');
        $page = Page::factory()->create();
        $page->mediaUsages()->create([
            'media_id' => $asset->id,
            'collection_name' => 'featured_image',
            'order_column' => 0,
        ]);
        $url = $asset->getUrl();
        $files = Storage::disk('public')->allFiles();

        Livewire::test(ListMedia::class)
            ->callTableAction('details', $asset, data: [
                'title' => '<b>Canonical title</b>',
                'alt_text' => '<script>alert(1)</script>Accessible description',
            ])
            ->assertHasNoTableActionErrors();

        $asset->refresh();
        $this->assertSame('Canonical title', $asset->name);
        $this->assertSame('alert(1)Accessible description', $asset->altText());
        $this->assertSame($assetKey, $asset->asset_key);
        $this->assertSame($url, $asset->getUrl());
        $this->assertSame($asset->id, $page->refresh()->featuredImage()?->id);
        $this->assertDatabaseCount('media', 1);
        $this->assertSame($files, Storage::disk('public')->allFiles());

        Livewire::test(ListMedia::class)
            ->assertSee('Canonical title');

        $asset->asset_key = (string) Str::ulid();

        try {
            $asset->save();
            $this->fail('The canonical asset key must be immutable.');
        } catch (\LogicException) {
            $this->assertSame($assetKey, $asset->fresh()->asset_key);
        }
    }

    public function test_legacy_asset_without_asset_key_keeps_its_existing_path_and_url(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $legacy = $admin->addMedia(UploadedFile::fake()->image('legacy-name.jpg'))
            ->toMediaCollection('media_library', 'public');

        $this->assertNull($legacy->asset_key);
        $this->assertSame('legacy-name.jpg', $legacy->originalFilename());
        $this->assertStringContainsString('/storage/'.$legacy->id.'/legacy-name.jpg', $legacy->getUrl());
        Storage::disk('public')->assertExists($legacy->id.'/legacy-name.jpg');
    }

    public function test_spatie_conversions_use_the_canonical_asset_path(): void
    {
        Storage::fake('public');
        $assetKey = (string) Str::ulid();
        $page = Page::factory()->create();

        $media = $page->addMedia(UploadedFile::fake()->image('conversion-source.jpg', 800, 600))
            ->usingFileName($assetKey.'.jpg')
            ->withProperties([
                'asset_key' => $assetKey,
                'original_filename' => 'conversion-source.jpg',
                'checksum_sha256' => str_repeat('b', 64),
            ])
            ->toMediaCollection('featured_image', 'public');

        $this->assertTrue($media->hasGeneratedConversion('thumb'));
        Storage::disk('public')->assertExists('media/'.$assetKey.'/'.$assetKey.'.jpg');
        Storage::disk('public')->assertExists('media/'.$assetKey.'/conversions/'.$assetKey.'-thumb.jpg');
    }

    public function test_modal_navigation_and_non_image_details_are_available(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $older = $admin->addMedia(UploadedFile::fake()->image('older.jpg'))
            ->toMediaCollection('media_library', 'public');
        $newer = $admin->addMedia(UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj"))
            ->toMediaCollection('media_library', 'public');

        $component = Livewire::test(ListMedia::class);
        $this->assertSame($older->id, $component->instance()->adjacentMediaId($newer, 'next'));
        $this->assertSame($newer->id, $component->instance()->adjacentMediaId($older, 'previous'));

        $component
            ->mountTableAction('details', $newer)
            ->assertSee('document.pdf')
            ->assertSee('application/pdf')
            ->assertSee('قبلی')
            ->assertSee('بعدی')
            ->assertDontSee('متن جایگزین');
    }

    public function test_grid_and_list_are_independent_presentations_with_responsive_grid_css(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $admin->addMedia(UploadedFile::fake()->image('grid-item.jpg'))
            ->toMediaCollection('media_library', 'public');

        $gridComponent = Livewire::test(ListMedia::class)
            ->assertSet('mediaView', 'list')
            ->call('setMediaView', 'grid')
            ->assertSet('mediaView', 'grid')
            ->assertSeeHtml('media-library-grid')
            ->assertSeeHtml('media-grid-card__preview');
        $this->assertTrue($gridComponent->instance()->isGridView());
        $this->assertSame('filament.media.grid', $gridComponent->instance()->getTable()->getContent()?->name());

        $gridComponent
            ->call('setMediaView', 'list')
            ->assertSet('mediaView', 'list');
        $this->assertNull($gridComponent->instance()->getTable()->getContent());

        $theme = file_get_contents(resource_path('views/filament/theme.blade.php'));
        $card = file_get_contents(resource_path('views/filament/tables/columns/media-grid-card.blade.php'));

        $this->assertStringContainsString('.media-library-grid', $theme);
        $this->assertStringContainsString('repeat(5, minmax(0, 1fr))', $theme);
        $this->assertStringContainsString('repeat(6, minmax(0, 1fr))', $theme);
        $this->assertStringContainsString('@media (max-width: 767px)', $theme);
        $this->assertStringContainsString('$record->displayTitle()', $card);
    }

    public function test_list_uses_media_name_for_display_search_and_sort_without_a_preview_record_action(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $zeta = $admin->addMedia(UploadedFile::fake()->image('physical-zeta.jpg'))
            ->usingName('Zeta title')
            ->toMediaCollection('media_library', 'public');
        $alpha = $admin->addMedia(UploadedFile::fake()->image('physical-alpha.jpg'))
            ->usingName('Alpha title')
            ->toMediaCollection('media_library', 'public');

        Livewire::test(ListMedia::class)
            ->assertSee('نام رسانه')
            ->assertSee('Alpha title')
            ->searchTable('Alpha title')
            ->assertCanSeeTableRecords([$alpha])
            ->assertCanNotSeeTableRecords([$zeta]);

        Livewire::test(ListMedia::class)
            ->sortTable('name')
            ->assertCanSeeTableRecords([$alpha, $zeta], inOrder: true);

        $resource = file_get_contents(app_path('Filament/Resources/MediaResource.php'));
        $preview = file_get_contents(resource_path('views/filament/tables/columns/media-preview.blade.php'));

        $this->assertStringNotContainsString("recordAction('details')", $resource);
        $this->assertStringNotContainsString("'view' =>", $resource);
        $this->assertStringContainsString("TextColumn::make('name')", $resource);
        $this->assertStringNotContainsString("TextColumn::make('file_name')", $resource);
        $this->assertStringNotContainsString('<a ', $preview);
        $this->assertStringNotContainsString('wire:click', $preview);
    }
}
