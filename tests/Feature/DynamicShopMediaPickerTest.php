<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Models\Media;
use App\Models\Page;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DynamicShopMediaPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_shop_reuses_a_central_image_by_media_id_across_search_save_reload_and_metadata_edits(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $asset = $admin
            ->addMedia(UploadedFile::fake()->image('searchable-library-name.jpg', 1200, 630))
            ->usingName('Searchable Hero Title')
            ->toMediaCollection('media_library', 'public');
        Media::query()->create([
            'model_type' => User::class,
            'model_id' => $admin->id,
            'uuid' => fake()->uuid(),
            'collection_name' => 'media_library',
            'name' => 'Not an image',
            'file_name' => 'not-an-image.pdf',
            'mime_type' => 'application/pdf',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $page = Page::factory()->create();
        PageResource::syncFeaturedImage($page, $asset->id);

        $template = Template::query()->create([
            'title' => 'Dynamic Shop Media',
            'slug' => 'dynamic-shop-media',
            'type' => 'shop_index',
            'status' => 'draft',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [[
                'type' => 'template_shop_complete',
                'data' => ['title' => 'Media Shop'],
            ]],
        ]);

        $this->actingAs($admin);
        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])
            ->assertOk();
        $blockKey = array_key_first($component->get('data.blocks'));
        $backgroundPath = "data.blocks.{$blockKey}.data.background_media_id";
        $categoryPath = "data.blocks.{$blockKey}.data.all_categories_media_id";
        $html = $component->html();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'Searchable Hero Title'));
        $this->assertStringContainsString('searchable-library-name.jpg', $html);
        $this->assertStringNotContainsString('not-an-image.pdf', $html);
        $this->assertDatabaseHas('media_usages', [
            'media_id' => $asset->id,
            'usable_type' => Page::class,
            'usable_id' => $page->id,
        ]);

        $mediaCount = Media::query()->count();
        $fileCount = count(Storage::disk('public')->allFiles());
        $component
            ->set($backgroundPath, $asset->id)
            ->set($categoryPath, $asset->id)
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $template->fresh()->blocks[0]['data'];
        $this->assertSame($asset->id, $saved['background_media_id']);
        $this->assertSame($asset->id, $saved['all_categories_media_id']);
        $this->assertArrayNotHasKey('background_image', $saved);
        $this->assertArrayNotHasKey('all_categories_image', $saved);
        $this->assertSame($mediaCount, Media::query()->count());
        $this->assertCount($fileCount, Storage::disk('public')->allFiles());

        $reloaded = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $reloadedKey = array_key_first($reloaded->get('data.blocks'));
        $reloaded
            ->assertSet("data.blocks.{$reloadedKey}.data.background_media_id", $asset->id)
            ->assertSet("data.blocks.{$reloadedKey}.data.all_categories_media_id", $asset->id);

        $asset->name = 'Renamed Hero Title';
        $asset->setCustomProperty('alt_text', 'Changed alt text');
        $asset->save();
        $template->update(['status' => 'published']);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee($asset->fresh()->getUrl(), false);
        $this->assertSame($asset->id, $template->fresh()->blocks[0]['data']['background_media_id']);
        $this->assertSame($mediaCount, Media::query()->count());
        $this->assertCount($fileCount, Storage::disk('public')->allFiles());
    }

    public function test_shared_id_picker_keeps_media_data_component_local_and_searches_title_and_filename(): void
    {
        $source = file_get_contents(resource_path('views/filament/forms/components/media-library-picker.blade.php'));

        $this->assertStringContainsString('images: @js($images)', $source);
        $this->assertStringContainsString('[image.title, image.name]', $source);
        $this->assertStringContainsString('.includes(query)', $source);
        $this->assertStringNotContainsString('window.__mediaLibraryImageItems', $source);
    }

    public function test_legacy_dynamic_shop_url_hydrates_to_media_id_and_is_canonicalized_on_save(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $asset = $admin
            ->addMedia(UploadedFile::fake()->image('legacy-shop.jpg'))
            ->toMediaCollection('media_library', 'public');
        $template = Template::query()->create([
            'title' => 'Legacy Dynamic Shop Media',
            'slug' => 'legacy-dynamic-shop-media',
            'type' => 'shop_index',
            'status' => 'draft',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [[
                'type' => 'template_shop_complete',
                'data' => [
                    'title' => 'Legacy Media Shop',
                    'background_image' => $asset->getUrl(),
                ],
            ]],
        ]);

        $this->actingAs($admin);
        $component = Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()]);
        $blockKey = array_key_first($component->get('data.blocks'));

        $component
            ->assertSet("data.blocks.{$blockKey}.data.background_media_id", $asset->id)
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $template->fresh()->blocks[0]['data'];
        $this->assertSame($asset->id, $saved['background_media_id']);
        $this->assertArrayNotHasKey('background_image', $saved);
        $this->assertDatabaseCount('media', 1);
    }
}
