<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicGlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_search_queries_all_available_public_sources(): void
    {
        Product::factory()->published()->create(['title' => 'Needle Product']);
        Project::factory()->published()->create(['title' => 'Needle Project']);
        Post::factory()->published()->create(['title' => 'Needle Post']);
        Service::query()->create([
            'name' => 'Needle Service',
            'slug' => 'needle-service',
            'excerpt' => 'Needle',
            'status' => Service::STATUS_ACTIVE,
        ]);

        $this->get(route('search.index', ['q' => 'Needle']))
            ->assertOk()
            ->assertSee('Needle Product')
            ->assertSee('Needle Project')
            ->assertSee('Needle Service')
            ->assertSee('Needle Post')
            ->assertSee('noindex', false);
    }

    public function test_requested_sources_limit_domain_queries_and_results(): void
    {
        Product::factory()->published()->create(['title' => 'Scoped Product']);
        Project::factory()->published()->create(['title' => 'Scoped Project']);
        Post::factory()->published()->create(['title' => 'Scoped Post']);
        Service::query()->create([
            'name' => 'Scoped Service',
            'slug' => 'scoped-service',
            'status' => Service::STATUS_ACTIVE,
        ]);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('search.index', ['q' => 'Scoped', 'types' => ['project', 'service']]))
            ->assertOk()
            ->assertSee('Scoped Project')
            ->assertSee('Scoped Service')
            ->assertDontSee('Scoped Product')
            ->assertDontSee('Scoped Post');

        $sql = implode(' ', $queries);
        $this->assertStringContainsString('projects', $sql);
        $this->assertStringContainsString('services', $sql);
        $this->assertStringNotContainsString('from "products"', $sql);
        $this->assertStringNotContainsString('from "posts"', $sql);
    }

    public function test_product_and_post_only_searches_are_supported(): void
    {
        Product::factory()->published()->create(['title' => 'Only Product']);
        Post::factory()->published()->create(['title' => 'Only Post']);

        $this->get(route('search.index', ['q' => 'Only', 'types' => ['product']]))
            ->assertOk()
            ->assertSee('Only Product')
            ->assertDontSee('Only Post');

        $this->get(route('search.index', ['q' => 'Only', 'types' => ['post']]))
            ->assertOk()
            ->assertSee('Only Post')
            ->assertDontSee('Only Product');
    }

    public function test_invalid_types_and_empty_queries_are_rejected(): void
    {
        $this->from('/')->get(route('search.index', ['q' => 'valid', 'types' => ['invalid']]))
            ->assertRedirect('/')
            ->assertSessionHasErrors('types.0');

        $this->from('/')->get(route('search.index', ['q' => '']))
            ->assertRedirect('/')
            ->assertSessionHasErrors('q');
    }

    public function test_each_domain_preserves_its_public_lifecycle(): void
    {
        Product::factory()->draft()->create(['title' => 'Private Needle Product']);
        Project::factory()->draft()->create(['title' => 'Private Needle Project']);
        Post::factory()->draft()->create(['title' => 'Private Needle Post']);
        Service::query()->create([
            'name' => 'Private Needle Service',
            'slug' => 'private-needle-service',
            'status' => Service::STATUS_DRAFT,
        ]);

        $this->get(route('search.index', ['q' => 'Private Needle']))
            ->assertOk()
            ->assertDontSee('Private Needle Product')
            ->assertDontSee('Private Needle Project')
            ->assertDontSee('Private Needle Service')
            ->assertDontSee('Private Needle Post');
    }

    public function test_disabled_modules_are_absent_from_selector_and_never_queried(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        $this->seed();
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');
        app(SettingsService::class)->set('projects_enabled', false, 'projects', 'boolean');

        $html = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="product"', $html);
        $this->assertStringNotContainsString('value="project"', $html);

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('search.index', ['q' => 'Needle']))->assertOk();
        $sql = implode(' ', $queries);
        $this->assertStringNotContainsString('from "products"', $sql);
        $this->assertStringNotContainsString('from "projects"', $sql);
    }

    public function test_normal_header_render_executes_no_content_domain_queries(): void
    {
        Page::factory()->published()->create(['slug' => 'home']);
        $this->seed();
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get(route('home'))->assertOk();

        $sql = implode(' ', $queries);
        foreach (['products', 'projects', 'services', 'posts'] as $table) {
            $this->assertStringNotContainsString('from "'.$table.'"', $sql);
        }
    }
}
