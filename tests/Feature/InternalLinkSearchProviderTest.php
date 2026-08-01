<?php

namespace Tests\Feature;

use App\CMS\Actions\Registry\ActionTargetRegistry;
use App\CMS\InternalLinks\Registry\InternalLinkSearchRegistry;
use App\Models\Page;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Services\InternalLinkSearchService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalLinkSearchProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_providers_register_sources_in_deterministic_target_aligned_order(): void
    {
        $internalLinks = app(InternalLinkSearchRegistry::class);
        $actionTargets = app(ActionTargetRegistry::class);

        $this->assertSame(
            ['page', 'project', 'product', 'service'],
            $internalLinks->keys(),
        );
        $this->assertCount(4, $internalLinks->all());

        foreach ($internalLinks->keys() as $key) {
            $this->assertTrue($actionTargets->has($key));
            $this->assertSame($key, $internalLinks->get($key)?->key());
        }
    }

    public function test_search_returns_reference_results_for_only_public_entities(): void
    {
        $page = Page::factory()->published()->create(['title' => 'Needle Page']);
        Page::factory()->draft()->create(['title' => 'Needle Draft Page']);
        Page::factory()->published()->create([
            'title' => 'Needle Scheduled Page',
            'published_at' => now()->addDay(),
        ]);

        $project = Project::factory()->published()->create(['title' => 'Needle Project']);
        Project::factory()->draft()->create(['title' => 'Needle Draft Project']);

        $product = Product::factory()->published()->create([
            'title' => 'Needle Product',
            'has_stock' => false,
            'stock_status' => 'out_of_stock',
        ]);
        Product::factory()->draft()->create(['title' => 'Needle Draft Product']);

        $service = $this->service(
            'Needle Service',
            'needle-service',
            Service::STATUS_ACTIVE,
        );
        $this->service('Needle Draft Service', 'needle-draft-service', Service::STATUS_DRAFT);
        $this->service('Needle Inactive Service', 'needle-inactive-service', Service::STATUS_INACTIVE);
        $this->service('Needle Archived Service', 'needle-archived-service', Service::STATUS_ARCHIVED);
        $this->service(
            'Needle Scheduled Service',
            'needle-scheduled-service',
            Service::STATUS_PUBLISHED,
            now()->addDay(),
        );

        $results = app(InternalLinkSearchService::class)->searchResults('Needle');

        $this->assertSame(
            ['page', 'project', 'product', 'service'],
            array_column(array_map(fn ($result) => $result->toArray(), $results), 'target_key'),
        );
        $this->assertSame(
            [$page->id, $project->id, $product->id, $service->id],
            array_column(array_map(fn ($result) => $result->toArray(), $results), 'reference_id'),
        );
        $this->assertSame([
            route('pages.show', $page->slug),
            route('projects.show', $project->slug),
            route('shop.show', $product->slug),
            route('services.show', $service->slug),
        ], array_column(array_map(fn ($result) => $result->toArray(), $results), 'url'));
        $this->assertCount(4, $results);
    }

    public function test_module_availability_excludes_projects_and_products_without_unregistering_sources(): void
    {
        Page::factory()->published()->create(['title' => 'Module Search Page']);
        Project::factory()->published()->create(['title' => 'Module Search Project']);
        Product::factory()->published()->create(['title' => 'Module Search Product']);
        $service = $this->service(
            'Module Search Service',
            'module-search-service',
            Service::STATUS_PUBLISHED,
            now()->subMinute(),
        );
        app(SettingsService::class)->set('projects_enabled', false, 'projects', 'boolean');
        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');

        $results = app(InternalLinkSearchService::class)->searchResults('Module Search');

        $this->assertSame(['page', 'service'], array_map(
            fn ($result): string => $result->targetKey,
            $results,
        ));
        $this->assertSame($service->id, $results[1]->referenceId);
        $this->assertTrue(app(InternalLinkSearchRegistry::class)->has('project'));
        $this->assertTrue(app(InternalLinkSearchRegistry::class)->has('product'));
    }

    public function test_slug_search_and_slug_changes_produce_the_current_canonical_url(): void
    {
        $page = Page::factory()->published()->create([
            'title' => 'Unrelated Page Title',
            'slug' => 'discover-by-old-slug',
        ]);
        $search = app(InternalLinkSearchService::class);

        $old = $search->searchResults('old-slug');
        $page->update(['slug' => 'discover-by-new-slug']);
        $new = $search->searchResults('new-slug');

        $this->assertCount(1, $old);
        $this->assertCount(1, $new);
        $this->assertSame($page->id, $new[0]->referenceId);
        $this->assertSame(route('pages.show', 'discover-by-old-slug'), $old[0]->url);
        $this->assertSame(route('pages.show', 'discover-by-new-slug'), $new[0]->url);
    }

    public function test_search_limit_and_ranking_are_deterministic(): void
    {
        Page::factory()->published()->create(['title' => 'Rank']);
        Page::factory()->published()->create(['title' => 'Rank Prefix']);
        Page::factory()->published()->create(['title' => 'Other Rank Match']);

        $search = app(InternalLinkSearchService::class);
        $ranked = $search->searchResults('Rank', 50);
        $zeroLimit = $search->searchResults('Rank', 0);
        $bounded = $search->searchResults('Rank', 500);

        $this->assertSame(
            ['Rank', 'Rank Prefix', 'Other Rank Match'],
            array_map(fn ($result): string => $result->title, $ranked),
        );
        $this->assertCount(1, $zeroLimit);
        $this->assertCount(3, $bounded);
        $this->assertSame([], $search->searchResults('x'));
        $this->assertSame([], $search->searchResults("' OR 1=1 --"));
    }

    public function test_admin_endpoint_preserves_legacy_shape_and_adds_references(): void
    {
        $page = Page::factory()->published()->create([
            'title' => 'Endpoint Reference Page',
            'slug' => 'endpoint-reference-page',
        ]);

        $this->getJson(route('admin.internal-links.search', ['q' => 'Endpoint']))
            ->assertUnauthorized();

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.internal-links.search', ['q' => 'Endpoint']))
            ->assertForbidden();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.internal-links.search', ['q' => 'Endpoint']))
            ->assertOk()
            ->assertJsonCount(1);

        $response->assertJsonPath('0.title', 'Endpoint Reference Page');
        $response->assertJsonPath('0.type', 'برگه');
        $response->assertJsonPath('0.url', route('pages.show', $page->slug));
        $response->assertJsonPath('0.subtitle', '/endpoint-reference-page');
        $response->assertJsonPath('0.target_key', 'page');
        $response->assertJsonPath('0.reference_id', $page->id);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('admin.internal-links.search', ['q' => 'x']))
            ->assertOk()
            ->assertExactJson([]);
    }

    private function service(
        string $name,
        string $slug,
        string $status,
        mixed $publishedAt = null,
    ): Service {
        return Service::query()->create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }
}
