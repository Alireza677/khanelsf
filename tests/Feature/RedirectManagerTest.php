<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Redirect;
use App\Models\Setting;
use App\Services\ModuleRedirectSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_redirect_returns_301_and_records_hit(): void
    {
        $redirect = Redirect::query()->create([
            'source_path' => 'old-page',
            'target_url' => '/new-page',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/old-page')
            ->assertStatus(301)
            ->assertRedirect('/new-page');

        $redirect->refresh();

        $this->assertSame('/old-page', $redirect->source_path);
        $this->assertSame(1, $redirect->hits_count);
        $this->assertNotNull($redirect->last_hit_at);
    }

    public function test_active_redirect_can_return_302(): void
    {
        Redirect::query()->create([
            'source_path' => '/temporary',
            'target_url' => '/target',
            'status_code' => 302,
            'is_active' => true,
        ]);

        $this->get('/temporary')
            ->assertStatus(302)
            ->assertRedirect('/target');
    }

    public function test_inactive_redirect_does_not_redirect(): void
    {
        Redirect::query()->create([
            'source_path' => '/inactive',
            'target_url' => '/target',
            'status_code' => 301,
            'is_active' => false,
        ]);

        $this->get('/inactive')->assertNotFound();
    }

    public function test_direct_redirect_loop_does_not_crash(): void
    {
        Redirect::query()->create([
            'source_path' => '/loop',
            'target_url' => '/loop',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/loop')->assertNotFound();
    }

    public function test_admin_paths_are_not_redirected(): void
    {
        Redirect::query()->create([
            'source_path' => '/admin',
            'target_url' => '/',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get('/admin')
            ->assertNotFound();
    }

    public function test_disabled_project_redirect_suggestions_create_redirect_records(): void
    {
        $category = ProjectCategory::factory()->create(['slug' => 'case-studies']);
        Project::factory()->published()->for($category, 'category')->create(['slug' => 'case-study']);
        $this->setting('projects_enabled', '0', 'projects', 'boolean');

        $count = app(ModuleRedirectSuggestionService::class)->createProjectRedirects('/portfolio', 301);

        $this->assertSame(3, $count);
        $this->assertDatabaseHas('redirects', ['source_path' => '/projects', 'target_url' => '/portfolio']);
        $this->assertDatabaseHas('redirects', ['source_path' => '/projects/category/case-studies', 'target_url' => '/portfolio']);
        $this->assertDatabaseHas('redirects', ['source_path' => '/projects/case-study', 'target_url' => '/portfolio']);

        $this->get('/projects/case-study')
            ->assertStatus(301)
            ->assertRedirect('/portfolio');
    }

    public function test_disabled_shop_redirect_suggestions_create_redirect_records(): void
    {
        $category = ProductCategory::factory()->create(['slug' => 'products']);
        Product::factory()->published()->for($category, 'category')->create(['slug' => 'product-a']);
        $this->setting('shop_enabled', '0', 'shop', 'boolean');

        $count = app(ModuleRedirectSuggestionService::class)->createShopRedirects('/contact', 302);

        $this->assertSame(5, $count);
        $this->assertDatabaseHas('redirects', ['source_path' => '/shop', 'target_url' => '/contact']);
        $this->assertDatabaseHas('redirects', ['source_path' => '/cart', 'target_url' => '/contact']);
        $this->assertDatabaseHas('redirects', ['source_path' => '/checkout', 'target_url' => '/contact']);
        $this->assertDatabaseHas('redirects', ['source_path' => '/shop/category/products', 'target_url' => '/contact']);
        $this->assertDatabaseHas('redirects', ['source_path' => '/shop/product-a', 'target_url' => '/contact']);

        $this->get('/shop/product-a')
            ->assertStatus(302)
            ->assertRedirect('/contact');
    }

    public function test_sitemap_does_not_include_redirect_source_urls(): void
    {
        Page::factory()->published()->create(['slug' => 'target-page']);
        Redirect::query()->create([
            'source_path' => '/old-target-page',
            'target_url' => '/target-page',
            'status_code' => 301,
            'is_active' => true,
        ]);

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertSee('/target-page', false)
            ->assertDontSee('/old-target-page', false);
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
