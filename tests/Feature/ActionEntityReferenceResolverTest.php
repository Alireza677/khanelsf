<?php

namespace Tests\Feature;

use App\CMS\Actions\Contracts\ActionResolver;
use App\CMS\Actions\Data\ActionDestination;
use App\CMS\Actions\Data\ResolutionContext;
use App\CMS\Actions\Enums\ActionResolutionStatus;
use App\CMS\Actions\Enums\ResolutionMode;
use App\CMS\Actions\Resolution\RuntimeActionResolver;
use App\Models\Page;
use App\Models\Product;
use App\Models\Project;
use App\Models\Service;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ActionEntityReferenceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_resolver_is_registered_by_both_contract_and_concrete_type(): void
    {
        $this->assertSame(
            app(RuntimeActionResolver::class),
            app(ActionResolver::class),
        );
    }

    public function test_page_resolution_uses_canonical_public_and_preview_routes(): void
    {
        $page = Page::factory()->published()->create(['slug' => 'about-us']);
        $resolver = app(ActionResolver::class);

        $production = $resolver->resolve($this->destination('page', $page->id), $this->production());
        $this->assertSame(ActionResolutionStatus::Resolved, $production->status);
        $this->assertSame('/about-us', $production->url);

        $page->update(['slug' => 'renamed-about']);
        $renamed = $resolver->resolve($this->destination('page', $page->id), $this->production());
        $this->assertSame('/renamed-about', $renamed->url);

        $page->update(['slug' => 'home']);
        $home = $resolver->resolve($this->destination('page', $page->id), $this->production());
        $this->assertSame('/', $home->url);

        $page->update(['slug' => 'contact']);
        $contact = $resolver->resolve($this->destination('page', $page->id), $this->production());
        $this->assertSame('/contact', $contact->url);

        $page->update(['status' => 'draft', 'published_at' => null]);
        $draft = $resolver->resolve($this->destination('page', $page->id), $this->production());
        $preview = $resolver->resolve($this->destination('page', $page->id), $this->preview());

        $this->assertSame('entity_unpublished', $draft->reason);
        $this->assertSame('/admin/preview/pages/'.$page->id, $preview->url);
        $this->assertSame('entity_not_found', $resolver->resolve(
            $this->destination('page', 999999),
            $this->production(),
        )->reason);
    }

    public function test_page_scheduled_and_route_unavailable_fail_closed(): void
    {
        $page = Page::factory()->published()->create([
            'slug' => 'scheduled-page',
            'published_at' => now()->addDay(),
        ]);
        $resolver = app(ActionResolver::class);

        $this->assertSame('entity_scheduled', $resolver->resolve(
            $this->destination('page', $page->id),
            $this->production(),
        )->reason);

        $page->update(['published_at' => now()->subDay()]);
        $originalRoutes = Route::getRoutes();
        Route::setRoutes(new RouteCollection);

        try {
            $result = $resolver->resolve(
                $this->destination('page', $page->id),
                $this->production(),
            );
        } finally {
            Route::setRoutes($originalRoutes);
        }

        $this->assertSame(ActionResolutionStatus::Unavailable, $result->status);
        $this->assertSame('route_unavailable', $result->reason);
        $this->assertNull($result->url);
    }

    public function test_project_resolution_honors_module_publication_and_preview(): void
    {
        $project = Project::factory()->published()->create(['slug' => 'reference-project']);
        $resolver = app(ActionResolver::class);

        $resolved = $resolver->resolve($this->destination('project', $project->id), $this->production());
        $this->assertSame('/projects/reference-project', $resolved->url);

        $project->update(['status' => 'draft', 'published_at' => null]);
        $this->assertSame('entity_unpublished', $resolver->resolve(
            $this->destination('project', $project->id),
            $this->production(),
        )->reason);
        $this->assertSame('/admin/preview/projects/'.$project->id, $resolver->resolve(
            $this->destination('project', $project->id),
            $this->preview(),
        )->url);

        app(SettingsService::class)->set('projects_enabled', false, 'projects', 'boolean');
        $this->assertSame('module_disabled', $resolver->resolve(
            $this->destination('project', $project->id),
            $this->preview(),
        )->reason);
    }

    public function test_product_resolution_ignores_stock_but_honors_module_and_publication(): void
    {
        $product = Product::factory()->published()->create([
            'slug' => 'reference-product',
            'has_stock' => false,
            'stock_status' => 'out_of_stock',
        ]);
        $resolver = app(ActionResolver::class);

        $resolved = $resolver->resolve(
            $this->destination('product', $product->id, true),
            $this->production(),
        );
        $this->assertSame('/shop/reference-product', $resolved->url);
        $this->assertSame('_blank', $resolved->target);
        $this->assertSame('noopener noreferrer', $resolved->rel);

        $product->update(['status' => 'draft', 'published_at' => null]);
        $this->assertSame('entity_unpublished', $resolver->resolve(
            $this->destination('product', $product->id),
            $this->production(),
        )->reason);
        $this->assertSame('/admin/preview/products/'.$product->id, $resolver->resolve(
            $this->destination('product', $product->id),
            $this->preview(),
        )->url);

        app(SettingsService::class)->set('shop_enabled', false, 'shop', 'boolean');
        $this->assertSame('module_disabled', $resolver->resolve(
            $this->destination('product', $product->id),
            $this->production(),
        )->reason);
    }

    public function test_service_resolution_uses_legacy_and_modern_canonical_lifecycle(): void
    {
        $resolver = app(ActionResolver::class);
        $legacy = $this->service('legacy-action-service', Service::STATUS_ACTIVE);
        $published = $this->service(
            'published-action-service',
            Service::STATUS_PUBLISHED,
            now()->subMinute(),
        );

        $this->assertSame('/services/legacy-action-service', $resolver->resolve(
            $this->destination('service', $legacy->id),
            $this->production(),
        )->url);
        $this->assertSame('/services/published-action-service', $resolver->resolve(
            $this->destination('service', $published->id),
            $this->production(),
        )->url);

        $states = [
            [Service::STATUS_INACTIVE, null, 'entity_inactive'],
            [Service::STATUS_DRAFT, null, 'entity_unpublished'],
            [Service::STATUS_ARCHIVED, null, 'entity_archived'],
            [Service::STATUS_PUBLISHED, now()->addDay(), 'entity_scheduled'],
        ];

        foreach ($states as $index => [$status, $publishedAt, $reason]) {
            $service = $this->service(
                'unavailable-action-service-'.$index,
                $status,
                $publishedAt,
            );
            $result = $resolver->resolve(
                $this->destination('service', $service->id),
                $this->production(),
            );

            $this->assertSame(ActionResolutionStatus::Unavailable, $result->status);
            $this->assertSame($reason, $result->reason);
            $this->assertNull($result->url);
        }

        $this->assertSame('preview_unavailable', $resolver->resolve(
            $this->destination('service', $legacy->id),
            $this->preview(),
        )->reason);
        $this->assertSame('entity_not_found', $resolver->resolve(
            $this->destination('service', 999999),
            $this->production(),
        )->reason);
    }

    public function test_all_entity_targets_distinguish_missing_entities_and_missing_routes(): void
    {
        $resolver = app(ActionResolver::class);

        foreach (['page', 'project', 'product', 'service'] as $type) {
            $missing = $resolver->resolve(
                $this->destination($type, 999999),
                $this->production(),
            );

            $this->assertSame(ActionResolutionStatus::Unresolved, $missing->status);
            $this->assertSame('entity_not_found', $missing->reason);
        }

        $entities = [
            'page' => Page::factory()->published()->create(),
            'project' => Project::factory()->published()->create(),
            'product' => Product::factory()->published()->create(),
            'service' => $this->service('route-check-service', Service::STATUS_ACTIVE),
        ];
        $originalRoutes = Route::getRoutes();
        Route::setRoutes(new RouteCollection);

        try {
            foreach ($entities as $type => $entity) {
                $production = $resolver->resolve(
                    $this->destination($type, $entity->getKey()),
                    $this->production(),
                );

                $this->assertSame(ActionResolutionStatus::Unavailable, $production->status);
                $this->assertSame('route_unavailable', $production->reason);
                $this->assertNull($production->url);
            }

            foreach (['page', 'project', 'product'] as $type) {
                $preview = $resolver->resolve(
                    $this->destination($type, $entities[$type]->getKey()),
                    $this->preview(),
                );

                $this->assertSame(ActionResolutionStatus::Unavailable, $preview->status);
                $this->assertSame('preview_unavailable', $preview->reason);
            }
        } finally {
            Route::setRoutes($originalRoutes);
        }
    }

    private function destination(
        string $type,
        int $referenceId,
        bool $newTab = false,
    ): ActionDestination {
        return new ActionDestination(
            type: $type,
            referenceId: $referenceId,
            openInNewTab: $newTab,
        );
    }

    private function production(): ResolutionContext
    {
        return new ResolutionContext(ResolutionMode::Production);
    }

    private function preview(): ResolutionContext
    {
        return new ResolutionContext(ResolutionMode::Preview);
    }

    private function service(
        string $slug,
        string $status,
        mixed $publishedAt = null,
    ): Service {
        return Service::query()->create([
            'name' => str($slug)->replace('-', ' ')->title()->toString(),
            'slug' => $slug,
            'status' => $status,
            'published_at' => $publishedAt,
        ]);
    }
}
