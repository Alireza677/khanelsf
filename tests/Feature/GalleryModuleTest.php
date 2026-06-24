<?php

namespace Tests\Feature;

use App\Filament\Resources\GalleryCategoryResource;
use App\Filament\Resources\GalleryResource;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Services\ModuleCleanupService;
use App\Services\ModuleRedirectSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_galleries_index_loads_and_published_gallery_loads(): void
    {
        $gallery = Gallery::factory()->create([
            'slug' => 'published-gallery',
            'title' => 'Published Gallery',
        ]);

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertSee('Published Gallery');

        $this->get(route('galleries.show', $gallery->slug))
            ->assertOk()
            ->assertSee('Published Gallery')
            ->assertSee('rel="canonical"', false)
            ->assertSee('property="og:title"', false);
    }

    public function test_draft_gallery_returns_404(): void
    {
        $gallery = Gallery::factory()->draft()->create(['slug' => 'draft-gallery']);

        $this->get(route('galleries.show', $gallery->slug))
            ->assertNotFound();
    }

    public function test_disabled_galleries_return_404_hide_menu_links_and_admin_navigation(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        Gallery::factory()->create(['slug' => 'hidden-gallery']);
        $this->menuWithGalleryLinks();

        $this->setting('galleries_enabled', '0', 'galleries', 'boolean');

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('/galleries', false);

        $this->get(route('galleries.index'))->assertNotFound();
        $this->get(route('galleries.show', 'hidden-gallery'))->assertNotFound();

        $this->assertDatabaseHas('galleries', ['slug' => 'hidden-gallery']);
        $this->assertFalse(GalleryResource::shouldRegisterNavigation());
        $this->assertFalse(GalleryCategoryResource::shouldRegisterNavigation());
    }

    public function test_disabled_gallery_urls_can_still_be_redirected(): void
    {
        Gallery::factory()->create(['slug' => 'old-gallery']);
        $this->setting('galleries_enabled', '0', 'galleries', 'boolean');

        app(ModuleRedirectSuggestionService::class)->createGalleryRedirects('/contact', 301);

        $this->get('/galleries/old-gallery')
            ->assertRedirect('/contact')
            ->assertStatus(301);
    }

    public function test_disabled_and_noindex_galleries_are_excluded_from_sitemap(): void
    {
        Gallery::factory()->create(['slug' => 'visible-gallery']);
        Gallery::factory()->noindex()->create(['slug' => 'noindex-gallery']);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee(route('galleries.index'), false)
            ->assertSee(route('galleries.show', 'visible-gallery'), false)
            ->assertDontSee('noindex-gallery');

        $this->setting('galleries_enabled', '0', 'galleries', 'boolean');

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertDontSee('/galleries', false);
    }

    public function test_gallery_redirect_suggestions_can_be_created(): void
    {
        $category = GalleryCategory::factory()->create(['slug' => 'events']);
        Gallery::factory()->create([
            'gallery_category_id' => $category->id,
            'slug' => 'event-gallery',
        ]);

        $this->setting('galleries_enabled', '0', 'galleries', 'boolean');

        $count = app(ModuleRedirectSuggestionService::class)->createGalleryRedirects('/media', 301);

        $this->assertSame(3, $count);
        $this->assertDatabaseHas('redirects', [
            'source_path' => '/galleries',
            'target_url' => '/media',
            'status_code' => 301,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('redirects', ['source_path' => '/galleries/category/events']);
        $this->assertDatabaseHas('redirects', ['source_path' => '/galleries/event-gallery']);
    }

    public function test_cleanup_galleries_deletes_galleries_and_categories(): void
    {
        Gallery::factory()->count(2)->create();
        GalleryCategory::factory()->count(2)->create();

        app(ModuleCleanupService::class)->deleteGalleries();

        $this->assertDatabaseCount('galleries', 0);
        $this->assertDatabaseCount('gallery_categories', 0);
    }

    public function test_project_page_can_show_related_galleries(): void
    {
        $project = Project::factory()->published()->create([
            'slug' => 'project-with-gallery',
            'title' => 'Project With Gallery',
        ]);
        Gallery::factory()->create([
            'project_id' => $project->id,
            'title' => 'Related Project Gallery',
        ]);

        $this->get(route('projects.show', $project->slug))
            ->assertOk()
            ->assertSee('Project Gallery')
            ->assertSee('Related Project Gallery');
    }

    public function test_gallery_preview_is_auth_protected_and_noindexed(): void
    {
        $gallery = Gallery::factory()->draft()->create([
            'title' => 'Draft Gallery Preview',
        ]);

        $this->get(route('admin.preview.galleries.show', $gallery))
            ->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.galleries.show', $gallery))
            ->assertOk()
            ->assertSee('Draft Gallery Preview')
            ->assertSee('content="noindex, nofollow"', false);
    }

    public function test_video_gallery_renders_safe_embed_or_external_link(): void
    {
        $youtube = Gallery::factory()->create([
            'slug' => 'youtube-gallery',
            'title' => 'YouTube Gallery',
            'type' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ]);

        $this->get(route('galleries.show', $youtube->slug))
            ->assertOk()
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('<iframe', false);

        $external = Gallery::factory()->create([
            'slug' => 'external-video-gallery',
            'title' => 'External Video Gallery',
            'type' => 'video',
            'video_url' => 'https://example.com/video-page',
        ]);

        $this->get(route('galleries.show', $external->slug))
            ->assertOk()
            ->assertSee('This video opens on the external video provider.')
            ->assertSee('https://example.com/video-page', false);
    }

    private function menuWithGalleryLinks(): void
    {
        $menu = Menu::query()->create([
            'title' => 'Main Menu',
            'slug' => 'main-menu',
            'location' => 'main',
            'status' => 'active',
        ]);

        foreach ([['Galleries', '/galleries'], ['Gallery Detail', '/galleries/sample'], ['Blog', '/blog']] as $index => [$title, $url]) {
            $menu->items()->create([
                'title' => $title,
                'url' => $url,
                'target' => '_self',
                'sort_order' => $index + 1,
                'status' => 'active',
            ]);
        }
    }

    private function setting(string $key, string $value, string $group, string $type): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'type' => $type,
            ],
        );
    }
}
