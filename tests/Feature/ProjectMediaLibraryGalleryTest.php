<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaResource\Pages\UploadMedia;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\ServiceResource;
use App\Models\Media;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use Filament\Forms\Components\ViewField;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use ReflectionProperty;
use Tests\TestCase;

class ProjectMediaLibraryGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        foreach ([ProjectResource::class, ProductResource::class, ServiceResource::class] as $resource) {
            (new ReflectionProperty($resource, 'mediaLibraryImageItemsCache'))->setValue(null, null);
        }
    }

    public function test_create_references_existing_images_in_selection_order_without_copies_and_keeps_featured_single(): void
    {
        $first = $this->asset('first.jpg');
        $second = $this->asset('second.jpg');
        $files = Storage::disk('public')->allFiles();
        $component = Livewire::test(CreateProject::class)->assertSee('انتخاب تصاویر گالری');
        $field = $component->instance()->form->getComponent('data.gallery_media_ids');
        $this->assertInstanceOf(ViewField::class, $field);
        $this->assertSame('filament.forms.components.media-library-multiple-picker', $field->getView());

        $component->fillForm(['title' => 'Library project', 'slug' => 'library-project', 'services' => [], 'attributes' => [], 'status' => 'draft', 'sort_order' => 0])
            ->set('data.featured_media_id', $first->id)
            ->set('data.gallery_media_ids', [$second->id, $first->id, $second->id])
            ->call('create')->assertHasNoFormErrors();

        $project = Project::query()->where('slug', 'library-project')->firstOrFail();
        $this->assertSame([$second->id, $first->id], $project->galleryImages()->pluck('id')->all());
        $this->assertSame($first->id, $project->featuredImage()->id);
        $this->assertDatabaseCount('media', 2);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_edit_hydrates_adds_reorders_and_detaches_without_deleting_library_files(): void
    {
        $first = $this->asset('first.jpg');
        $second = $this->asset('second.jpg');
        $third = $this->asset('third.jpg');
        $project = Project::factory()->create();
        ProjectResource::syncFeaturedImage($project, $first->id);
        ProjectResource::syncMediaLibraryCollection($project, 'gallery', [$first->id, $second->id]);
        $files = Storage::disk('public')->allFiles();

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertSet('data.gallery_media_ids', [(string) $first->id, (string) $second->id])
            ->assertSee('first.jpg')->assertSee('second.jpg')
            ->set('data.gallery_media_ids', [(string) $third->id, (string) $second->id])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame([$third->id, $second->id], $project->fresh()->galleryImages()->pluck('id')->all());
        $this->assertSame($first->id, $project->fresh()->featuredImage()->id);
        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertSet('data.gallery_media_ids', [(string) $third->id, (string) $second->id]);
        $this->assertDatabaseHas('media', ['id' => $first->id]);
        $this->assertSame($files, Storage::disk('public')->allFiles());

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->set('data.gallery_media_ids', [])
            ->call('save')->assertHasNoFormErrors();
        $this->assertCount(0, $project->fresh()->galleryImages());
        $this->assertSame($first->id, $project->fresh()->featuredImage()->id);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_legacy_images_and_new_references_round_trip_together_without_changing_media_identity(): void
    {
        $project = Project::factory()->create();
        $legacy = $project->addMedia(UploadedFile::fake()->image('legacy.jpg'))->toMediaCollection('gallery', 'public');
        $source = $this->asset('shared.jpg');
        $legacyUrl = $legacy->getUrl();
        $sourceUrl = $source->getUrl();
        $files = Storage::disk('public')->allFiles();

        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertSet('data.gallery_media_ids', ["existing:{$legacy->id}"])
            ->assertSee('legacy.jpg')
            ->set('data.gallery_media_ids', [(string) $source->id, "existing:{$legacy->id}"])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame([$source->id, $legacy->id], $project->fresh()->galleryImages()->pluck('id')->all());
        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->assertSet('data.gallery_media_ids', [(string) $source->id, "existing:{$legacy->id}"]);
        $source->update(['name' => 'Updated title', 'custom_properties' => ['alt_text' => 'Updated alt']]);
        $this->assertSame([$sourceUrl, $legacyUrl], $project->fresh()->galleryImages()->map->getUrl()->all());
        $this->assertDatabaseCount('media', 2);
        $this->assertSame($files, Storage::disk('public')->allFiles());
    }

    public function test_existing_uploader_and_refresh_add_new_assets_without_resetting_unsaved_gallery_state(): void
    {
        $first = $this->asset('already-selected.jpg');
        $project = Project::factory()->create();
        $component = Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])
            ->set('data.gallery_media_ids', [(string) $first->id]);

        Livewire::test(UploadMedia::class)
            ->set('data.files', [UploadedFile::fake()->image('new-upload.jpg')])
            ->call('save')->assertHasNoFormErrors();
        $new = Media::query()->where('original_filename', 'new-upload.jpg')->firstOrFail();

        $component->callFormComponentAction('gallery_media_ids', 'refreshLibrary')
            ->assertSet('data.gallery_media_ids', [(string) $first->id])
            ->assertDispatched('media-library-images-refreshed', fn ($event, $params): bool => $params['statePath'] === 'data.gallery_media_ids'
                && in_array($new->id, array_column($params['images'], 'id'), true))
            ->set('data.gallery_media_ids', [(string) $new->id, (string) $first->id])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame([$new->id, $first->id], $project->fresh()->galleryImages()->pluck('id')->all());
        $this->assertDatabaseCount('media', 2);
    }

    public function test_shared_gallery_contract_preserves_mixed_order_for_services_and_products_and_rejects_foreign_legacy_ids(): void
    {
        $source = $this->asset('central.jpg');
        $foreign = Project::factory()->create()->addMedia(UploadedFile::fake()->image('foreign.jpg'))->toMediaCollection('gallery', 'public');

        foreach ([
            [ProductResource::class, Product::factory()->create()],
            [ServiceResource::class, Service::query()->create(['name' => 'Gallery service', 'slug' => 'gallery-service'])],
        ] as [$resource, $record]) {
            $legacy = $record->addMedia(UploadedFile::fake()->image('owned.jpg'))->toMediaCollection('gallery', 'public');
            $resource::syncMediaLibraryCollection($record, 'gallery', ["existing:{$foreign->id}", "existing:{$legacy->id}", $source->id, $source->id]);
            $this->assertSame([$legacy->id, $source->id], $record->fresh()->galleryImages()->pluck('id')->all());
            $this->assertSame(["existing:{$legacy->id}", (string) $source->id], $resource::mediaLibraryCollectionState($record, 'gallery'));
            $resource::syncMediaLibraryCollection($record, 'gallery', ["existing:{$legacy->id}"]);
            $this->assertSame([$legacy->id], $record->fresh()->galleryImages()->pluck('id')->all());
        }

        $this->assertDatabaseHas('media', ['id' => $source->id]);
        $this->assertDatabaseHas('media', ['id' => $foreign->id]);
    }

    public function test_public_project_renders_reference_gallery_in_saved_order(): void
    {
        $first = $this->asset('public-first.jpg');
        $second = $this->asset('public-second.jpg');
        $first->update(['custom_properties' => ['alt_text' => 'First image alt']]);
        $project = Project::factory()->published()->create();
        ProjectResource::syncMediaLibraryCollection($project, 'gallery', [$second->id, $first->id]);
        Template::query()->create([
            'title' => 'Project gallery test', 'slug' => 'project-gallery-test', 'type' => 'project_single',
            'status' => 'published', 'is_default' => true, 'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'project_gallery', 'data' => []]],
        ]);

        $this->get(route('projects.show', $project->slug))->assertOk()->assertSee('First image alt');
        $html = view('partials.blocks.project_gallery', ['data' => [], 'context' => ['model' => $project->fresh()]])->render();
        $this->assertLessThan(strpos($html, $first->getUrl()), strpos($html, $second->getUrl()));
    }

    private function asset(string $name): Media
    {
        return auth()->user()->addMedia(UploadedFile::fake()->image($name))->toMediaCollection('media_library', 'public');
    }
}
