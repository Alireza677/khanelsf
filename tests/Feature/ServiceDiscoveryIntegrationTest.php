<?php

namespace Tests\Feature;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Project;
use App\Models\Service;
use App\Services\InternalLinkSearchService;
use App\Services\NavigationSourceVisibility;
use App\Services\SeoService;
use App\Services\ServiceQueryService;
use App\Services\SitemapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class ServiceDiscoveryIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_only_renders_public_lifecycles_in_stable_order_without_lazy_loading(): void
    {
        $this->service('Published Second', 'published-second', Service::STATUS_PUBLISHED, 2);
        $this->service('Legacy First', 'legacy-first', Service::STATUS_ACTIVE, 1);
        $this->service('Draft Hidden', 'draft-hidden', Service::STATUS_DRAFT);
        $this->service('Inactive Hidden', 'inactive-hidden', Service::STATUS_INACTIVE);
        $this->service('Archived Hidden', 'archived-hidden', Service::STATUS_ARCHIVED);
        $this->service(
            'Future Hidden',
            'future-hidden',
            Service::STATUS_PUBLISHED,
            publishedAt: now()->addDay(),
        );

        $services = app(ServiceQueryService::class)->paginateArchive();
        $this->assertTrue($services->getCollection()->every->relationLoaded('media'));

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSeeInOrder(['Legacy First', 'Published Second'])
            ->assertDontSee('Draft Hidden')
            ->assertDontSee('Inactive Hidden')
            ->assertDontSee('Archived Hidden')
            ->assertDontSee('Future Hidden');
    }

    public function test_archive_supports_pagination_and_an_accessible_empty_state(): void
    {
        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('هنوز خدمتی منتشر نشده است.');

        foreach (range(1, 13) as $index) {
            $this->service(
                'Service '.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                'service-'.$index,
                Service::STATUS_PUBLISHED,
                $index,
            );
        }

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('?page=2', false)
            ->assertSee('Service 01')
            ->assertDontSee('Service 13');

        $this->get(route('services.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Service 13');
    }

    public function test_archive_metadata_contains_canonical_social_metadata_and_breadcrumb_schema(): void
    {
        $response = $this->get(route('services.index'));

        $response
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('services.index').'">', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('name="twitter:card"', false)
            ->assertSee('"@type":"CollectionPage"', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_navigation_source_is_available_in_picker_resolves_without_manual_url_and_fails_closed(): void
    {
        $registry = app(NavigationSourceRegistry::class);
        $visibility = app(NavigationSourceVisibility::class);
        $source = $registry->find('services.archive');

        $this->assertNotNull($source);
        $this->assertSame('/services', $source->resolve());
        $this->assertContains(
            'services.archive',
            collect($visibility->visibleSources())->pluck('source_key')->all(),
        );

        $menu = Menu::query()->create([
            'title' => 'Main',
            'slug' => 'main',
            'status' => 'active',
        ]);
        $item = $menu->items()->create([
            'type' => MenuItem::TYPE_SOURCE,
            'source_key' => 'services.archive',
            'title' => 'خدمات',
            'url' => null,
            'target' => '_self',
            'sort_order' => 0,
            'status' => 'active',
        ]);

        $this->assertNull($item->url);
        $this->assertSame('/services', $item->resolvedUrl());

        $routes = Route::getRoutes();

        try {
            app('router')->setRoutes(new RouteCollection);
            $this->assertFalse($source->isAvailable());
            $this->assertNull($source->resolve());
            $this->assertFalse($visibility->menuItemIsVisible($item));
        } finally {
            app('router')->setRoutes($routes);
        }

        $item->delete();
        $registry->available();
        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);
    }

    public function test_sitemap_contains_archive_and_only_public_unique_detail_urls(): void
    {
        $published = $this->service('Published', 'sitemap-published', Service::STATUS_PUBLISHED);
        $legacy = $this->service('Legacy', 'sitemap-active', Service::STATUS_ACTIVE);
        $this->service('Draft', 'sitemap-draft', Service::STATUS_DRAFT);
        $this->service('Inactive', 'sitemap-inactive', Service::STATUS_INACTIVE);
        $this->service('Archived', 'sitemap-archived', Service::STATUS_ARCHIVED);
        $this->service(
            'Future',
            'sitemap-future',
            Service::STATUS_PUBLISHED,
            publishedAt: now()->addDay(),
        );

        $locations = app(SitemapService::class)->urls()->pluck('loc');

        $this->assertContains(route('services.index'), $locations);
        $this->assertContains(route('services.show', $published->slug), $locations);
        $this->assertContains(route('services.show', $legacy->slug), $locations);
        $this->assertFalse($locations->contains(fn (string $url): bool => str_contains($url, 'sitemap-draft')));
        $this->assertFalse($locations->contains(fn (string $url): bool => str_contains($url, 'sitemap-inactive')));
        $this->assertFalse($locations->contains(fn (string $url): bool => str_contains($url, 'sitemap-archived')));
        $this->assertFalse($locations->contains(fn (string $url): bool => str_contains($url, 'sitemap-future')));
        $this->assertSame($locations->count(), $locations->unique()->count());
    }

    public function test_service_schema_uses_real_absolute_data_and_omits_missing_media_and_fabricated_claims(): void
    {
        $service = $this->service(
            'Structural Design',
            'structural-design',
            Service::STATUS_PUBLISHED,
            excerpt: 'Professional structural design.',
        );
        $schema = app(SeoService::class)->forService($service)->schema;
        $serviceNode = collect($schema['@graph'])->firstWhere('@type', 'Service');
        $breadcrumb = collect($schema['@graph'])->firstWhere('@type', 'BreadcrumbList');

        $this->assertSame(route('services.show', $service->slug).'#service', $serviceNode['@id']);
        $this->assertSame(route('services.show', $service->slug), $serviceNode['url']);
        $this->assertSame('Professional structural design.', $serviceNode['description']);
        $this->assertSame('Organization', $serviceNode['provider']['@type']);
        $this->assertArrayNotHasKey('image', $serviceNode);
        $this->assertArrayNotHasKey('offers', $serviceNode);
        $this->assertArrayNotHasKey('aggregateRating', $serviceNode);
        $this->assertSame(
            [route('home'), route('services.index'), route('services.show', $service->slug)],
            collect($breadcrumb['itemListElement'])->pluck('item')->all(),
        );
    }

    public function test_internal_link_search_only_returns_public_services_with_canonical_labels(): void
    {
        $published = $this->service(
            'Structural Engineering',
            'structural-engineering',
            Service::STATUS_PUBLISHED,
            excerpt: 'LSF engineering service',
        );
        $this->service('Structural Draft', 'structural-draft', Service::STATUS_DRAFT);
        $this->service('Structural Inactive', 'structural-inactive', Service::STATUS_INACTIVE);

        $results = collect(app(InternalLinkSearchService::class)->search('Structural'));
        $result = $results->firstWhere('title', $published->name);

        $this->assertNotNull($result);
        $this->assertSame('خدمت', $result['type']);
        $this->assertSame(route('services.show', $published->slug), $result['url']);
        $this->assertFalse($results->contains('title', 'Structural Draft'));
        $this->assertFalse($results->contains('title', 'Structural Inactive'));
    }

    public function test_project_block_links_real_services_keeps_legacy_text_and_executes_no_queries(): void
    {
        $project = Project::factory()->make();
        $service = new Service([
            'name' => 'Published Service',
            'slug' => 'published-service',
            'status' => Service::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);
        $project->setRelation('relatedServices', collect([$service]));

        DB::enableQueryLog();
        $html = View::file(resource_path('views/partials/blocks/project_services.blade.php'), [
            'data' => [],
            'context' => ['model' => $project],
        ])->render();

        $this->assertSame([], DB::getQueryLog());
        $this->assertStringContainsString('href="/services/published-service"', $html);
        $this->assertStringContainsString('Published Service', $html);

        $legacy = Project::factory()->make([
            'services' => [['name' => 'Legacy Consulting']],
        ]);
        $legacy->setRelation('relatedServices', collect());
        DB::flushQueryLog();

        $legacyHtml = View::file(resource_path('views/partials/blocks/project_services.blade.php'), [
            'data' => [],
            'context' => ['model' => $legacy],
        ])->render();

        $this->assertSame([], DB::getQueryLog());
        $this->assertStringContainsString('Legacy Consulting', $legacyHtml);
        $this->assertStringNotContainsString('<a href=', $legacyHtml);
    }

    private function service(
        string $name,
        string $slug,
        string $status,
        int $sortOrder = 0,
        mixed $publishedAt = null,
        ?string $excerpt = null,
    ): Service {
        return Service::query()->create([
            'name' => $name,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'status' => $status,
            'sort_order' => $sortOrder,
            'published_at' => $publishedAt,
        ]);
    }
}
