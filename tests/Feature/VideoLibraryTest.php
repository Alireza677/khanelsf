<?php

namespace Tests\Feature;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\Contracts\VideoProviderAdapter;
use App\Filament\Resources\VideoCategoryResource;
use App\Filament\Resources\VideoResource;
use App\Filament\Resources\VideoResource\Pages\CreateVideo;
use App\Filament\Resources\VideoResource\Pages\EditVideo;
use App\Http\Controllers\VideoController;
use App\Models\Media;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoCategory;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\SitemapService;
use App\Services\Video\AparatVideoProvider;
use App\Services\VideoLibrarySettings;
use App\Services\VideoQueryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class VideoLibraryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Only these small migrations run, exclusively in the configured in-memory test database.
        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_05_30_000000_create_cms_tables.php',
            '2026_05_30_000001_create_media_table.php',
            '2026_06_11_000000_add_seo_core_fields_to_pages_and_posts.php',
            '2026_06_11_000001_add_published_at_to_pages.php',
            '2026_08_06_000000_add_client_authentication_fields_to_users_table.php',
            '2026_08_26_000001_create_media_usages_table.php',
            '2026_10_21_000000_create_video_library_tables.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }

        $real = new AparatVideoProvider;
        $this->mock(VideoProviderAdapter::class, function ($mock) use ($real): void {
            $mock->shouldReceive('parseUrl')->andReturnUsing($real->parseUrl(...));
            $mock->shouldReceive('trustedThumbnailUrl')->andReturnUsing($real->trustedThumbnailUrl(...));
            $mock->shouldReceive('embedUrl')->andReturnUsing($real->embedUrl(...));
            $mock->shouldReceive('fetchMetadata')->andReturn([]);
        });
    }

    public function test_source_identity_is_unique_slug_is_stable_and_descriptions_are_sanitized(): void
    {
        $video = $this->video('first', ['description' => '<p>Hello</p><script>alert(1)</script>']);
        $video->update(['title' => 'Changed title']);
        $this->assertSame('first', $video->slug);
        $this->assertStringNotContainsString('<script', $video->description);
        $this->assertSame('https://www.aparat.com/v/first', $video->source_url);

        $this->expectException(ValidationException::class);
        $this->video('duplicate', ['source_url' => 'https://aparat.com/v/first?from=other']);
    }

    public function test_archive_enforces_module_publication_schedule_and_bounded_pagination(): void
    {
        $this->video('draft');
        $public = $this->video('public', ['status' => 'published']);
        $this->video('scheduled', ['status' => 'published', 'published_at' => now()->addDay()]);
        $settings = app(VideoLibrarySettings::class);
        $query = app(VideoQueryService::class);
        $this->assertSame(0, $query->archiveQuery()->count());
        $this->withoutMiddleware()->get('/videos')->assertNotFound();

        app(SettingsService::class)->set('video_library_enabled', true);
        app(SettingsService::class)->set('video_library_per_page', 9999);
        $this->assertSame(48, $settings->perPage());
        $view = app(VideoController::class)->index(Request::create('/videos'), $settings, $query, app(SeoService::class));
        $this->assertSame([$public->id], $view->getData()['videos']->pluck('id')->all());
        $this->assertFalse(app('router')->has('videos.show'));
    }

    public function test_menu_source_and_sitemap_follow_toggle_without_creating_menu_items(): void
    {
        $settings = app(SettingsService::class);
        foreach (['projects_enabled', 'shop_enabled', 'public_services_enabled'] as $key) {
            $settings->set($key, false);
        }
        $sources = app(NavigationSourceRegistry::class);
        $this->assertFalse($sources->isAvailable('videos.archive'));
        $this->assertNotContains(route('videos.index'), app(SitemapService::class)->urls()->pluck('loc')->all());
        $settings->set('video_library_enabled', true);
        $this->assertTrue($sources->isAvailable('videos.archive'));
        $this->assertSame('/videos', $sources->resolve('videos.archive'));
        $this->assertContains(route('videos.index'), app(SitemapService::class)->urls()->pluck('loc')->all());
        $settings->set('video_library_enabled', false);
        $this->assertFalse($sources->isAvailable('videos.archive'));
    }

    public function test_admin_form_creates_and_edits_videos_without_losing_duration_or_categories(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        Storage::fake('public');
        $image = Media::create([
            'model_type' => User::class, 'model_id' => $user->id, 'collection_name' => 'media_library',
            'name' => 'Thumbnail', 'file_name' => 'thumbnail.png', 'mime_type' => 'image/png',
            'disk' => 'public', 'size' => 1, 'manipulations' => [], 'custom_properties' => [],
            'generated_conversions' => [], 'responsive_images' => [],
        ]);
        Storage::disk('public')->put($image->getPathRelativeToRoot(), 'image fixture');
        $category = VideoCategory::create(['name' => 'Training', 'slug' => 'training']);
        Livewire::test(CreateVideo::class)->fillForm([
            'title' => 'Training video', 'slug' => 'training-video', 'source_url' => 'https://aparat.com/v/Training1',
            'duration_seconds' => '01:05', 'categories' => [$category->id], 'status' => 'published',
            'featured_media_id' => $image->id,
        ])->call('create')->assertHasNoFormErrors();
        $video = Video::firstOrFail();
        $this->assertSame(65, $video->duration_seconds);
        $this->assertSame([$category->id], $video->categories()->pluck('video_categories.id')->all());
        Livewire::test(EditVideo::class, ['record' => $video->getRouteKey()])
            ->assertFormSet(['duration_seconds' => '01:05'])->fillForm(['title' => 'Edited title'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(65, $video->fresh()->duration_seconds);
        $this->assertSame('training-video', $video->fresh()->slug);
        $video->forceFill(['thumbnail_url' => 'https://static.cdn.asset.aparat.com/avt/cached.jpg'])->save();
        $this->assertSame($image->getUrl(), $video->fresh()->thumbnailUrl());
        $video->delete();
        $this->assertDatabaseHas('media', ['id' => $image->id]);
        $this->assertDatabaseMissing('media_usages', ['usable_type' => Video::class, 'usable_id' => $video->id]);
    }

    public function test_authorization_and_invalid_admin_inputs_fail_on_server(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user);
        $this->assertFalse(VideoResource::canCreate());
        $this->assertFalse(VideoCategoryResource::canCreate());
        Livewire::test(CreateVideo::class)->assertForbidden();
        $admin = User::factory()->admin()->create();
        $this->assertFalse(Gate::forUser($user)->allows('delete', $this->video('locked')));
        $admin->status = 'inactive';
        $this->assertFalse(Gate::forUser($admin)->allows('create', Video::class));

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(CreateVideo::class)->fillForm([
            'title' => 'Unsafe', 'source_url' => 'https://evil.test/v/Unsafe', 'duration_seconds' => '1:99',
            'related_url' => 'javascript:alert(1)', 'featured_media_id' => '9999999',
        ])->call('create')->assertHasFormErrors(['source_url', 'duration_seconds', 'related_url', 'featured_media_id']);
        $this->assertSame(1, Video::count());
    }

    public function test_provider_refresh_preserves_editorial_fields_and_manual_duration(): void
    {
        $video = $this->video('refresh', ['duration_seconds' => 120, 'description' => '<p>Editorial content</p>']);
        $real = new AparatVideoProvider;
        $this->mock(VideoProviderAdapter::class, function ($mock) use ($real): void {
            $mock->shouldReceive('parseUrl')->andReturnUsing($real->parseUrl(...));
            $mock->shouldReceive('trustedThumbnailUrl')->andReturnUsing($real->trustedThumbnailUrl(...));
            $mock->shouldReceive('fetchMetadata')->once()->andReturn([
                'thumbnail_url' => 'https://static.cdn.asset.aparat.com/avt/image.jpg', 'duration_seconds' => 65,
            ]);
        });
        $this->assertTrue($video->refreshMetadata());
        $this->assertSame('refresh', $video->title);
        $this->assertSame('<p>Editorial content</p>', $video->description);
        $this->assertSame(120, $video->duration_seconds);
        $this->assertSame('https://static.cdn.asset.aparat.com/avt/image.jpg', $video->thumbnailUrl());
    }

    private function video(string $id, array $attributes = []): Video
    {
        return Video::create(array_merge(['title' => $id, 'slug' => $id, 'source_url' => 'https://aparat.com/v/'.$id], $attributes));
    }
}
