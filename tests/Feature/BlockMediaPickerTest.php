<?php

namespace Tests\Feature;

use App\Filament\Resources\PageResource\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class BlockMediaPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_block_picker_has_accessible_select_replace_and_isolated_remove_controls(): void
    {
        $source = file_get_contents(resource_path('views/filament/forms/components/media-library-url-picker.blade.php'));

        $this->assertStringContainsString('data-block-media-picker-trigger', $source);
        $this->assertStringContainsString('x-on:click="open = true"', $source);
        $this->assertStringContainsString('data-block-media-picker-remove', $source);
        $this->assertStringContainsString('x-on:click.stop.prevent="clear()"', $source);
        $this->assertStringContainsString('this.selectedUrl = image.url', $source);
        $this->assertStringContainsString('this.selectedSourceId = image.id', $source);
        $this->assertStringContainsString('this.selectedUrl = null', $source);
        $this->assertStringContainsString('this.selectedSourceId = null', $source);
        $this->assertStringContainsString(':focus-within', $source);
        $this->assertStringContainsString('(pointer: coarse)', $source);
        $this->assertStringContainsString('dark:bg-gray-900', $source);
        $this->assertStringNotContainsString(".block-media-picker__preview,\n        .block-media-picker__empty", $source);
        $this->assertMatchesRegularExpression('/\.block-media-picker__preview\s*\{[^}]*aspect-ratio:\s*16\s*\/\s*9;[^}]*\}/s', $source);
        $this->assertMatchesRegularExpression('/\.block-media-picker__empty\s*\{[^}]*min-height:\s*12rem;[^}]*\}/s', $source);
        $this->assertMatchesRegularExpression('/\.block-media-picker__action\s*\{[^}]*background:\s*rgba\(0, 0, 0, \.92\);[^}]*opacity:\s*0;[^}]*visibility:\s*hidden;/s', $source);
        $this->assertMatchesRegularExpression('/\.block-media-picker:hover \.block-media-picker__action,[^{]*:focus-within \.block-media-picker__action\s*\{[^}]*opacity:\s*1;[^}]*visibility:\s*visible;/s', $source);
        $this->assertMatchesRegularExpression('/\.block-media-picker__remove\s*\{[^}]*left:\s*\.75rem;[^}]*top:\s*\.75rem;[^}]*z-index:\s*30;[^}]*background:\s*rgba\(0, 0, 0, \.82\)[^;]*;[^}]*color:\s*#fff/s', $source);
        $this->assertStringNotContainsString('Media::delete', $source);
        $this->assertStringNotContainsString('delete()', $source);
    }

    public function test_repeater_clear_is_scoped_and_save_reload_preserves_other_item_and_legacy_shape(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Page::factory()->create(['blocks' => [[
            'type' => 'feature_grid',
            'data' => [
                'block_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'schema_version' => 1,
                'template' => 'default',
                'content' => [
                    'section_title' => 'Images',
                    'items_mode' => 'static',
                    'items' => [
                        ['title' => 'First', 'image' => '/legacy-first.jpg'],
                        ['title' => 'Second', 'image' => '/legacy-second.jpg'],
                    ],
                ],
                'settings' => [],
            ],
        ]]]);
        Media::query()->create([
            'model_type' => User::class,
            'model_id' => auth()->id(),
            'uuid' => fake()->uuid(),
            'collection_name' => 'media_library',
            'name' => 'library-image',
            'file_name' => 'library-image.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'conversions_disk' => 'public',
            'size' => 100,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
        $mediaCount = Media::query()->count();
        $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $blockKey = array_key_first($component->get('data.blocks'));
        $items = $component->get("data.blocks.{$blockKey}.data.content.items");
        [$firstKey, $secondKey] = array_keys($items);
        $firstPath = "data.blocks.{$blockKey}.data.content.items.{$firstKey}.image";
        $secondPath = "data.blocks.{$blockKey}.data.content.items.{$secondKey}.image";

        $component
            ->assertSeeHtml('data-block-media-picker-trigger')
            ->assertSeeHtml('data-block-media-picker-remove')
            ->assertSet($firstPath, '/legacy-first.jpg')
            ->assertSet($secondPath, '/legacy-second.jpg')
            ->set($firstPath, null)
            ->assertSet($secondPath, '/legacy-second.jpg')
            ->set($firstPath, '/replacement.jpg')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $page->fresh()->blocks[0]['data'];
        $this->assertSame('/replacement.jpg', $saved['content']['items'][0]['image']);
        $this->assertSame('/legacy-second.jpg', $saved['content']['items'][1]['image']);
        $this->assertSame($mediaCount, Media::query()->count());
        $this->assertArrayNotHasKey('media_id', $saved['content']['items'][0]);

        $reloaded = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $reloadedBlockKey = array_key_first($reloaded->get('data.blocks'));
        $reloadedItems = $reloaded->get("data.blocks.{$reloadedBlockKey}.data.content.items");
        [$reloadedFirstKey, $reloadedSecondKey] = array_keys($reloadedItems);
        $reloaded
            ->assertSet("data.blocks.{$reloadedBlockKey}.data.content.items.{$reloadedFirstKey}.image", '/replacement.jpg')
            ->assertSet("data.blocks.{$reloadedBlockKey}.data.content.items.{$reloadedSecondKey}.image", '/legacy-second.jpg');
    }
}
