<?php

namespace Tests\Feature;

use App\CMS\Blocks\CTA\CTADataNormalizer;
use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\CMS\Templates\TemplatePublicationValidator;
use App\Http\Controllers\ServiceController;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use App\Services\ServiceTemplateContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ServiceProductionActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_route_supports_legacy_and_modern_publication_lifecycle(): void
    {
        $this->productionTemplate();
        $public = [
            $this->service(Service::STATUS_ACTIVE, ['slug' => 'legacy-active']),
            $this->service(Service::STATUS_PUBLISHED, ['slug' => 'published-without-date']),
            $this->service(Service::STATUS_PUBLISHED, [
                'slug' => 'published-in-past',
                'published_at' => now()->subDay(),
            ]),
        ];
        $hidden = [
            $this->service(Service::STATUS_DRAFT, ['slug' => 'draft-service']),
            $this->service(Service::STATUS_INACTIVE, ['slug' => 'inactive-service']),
            $this->service(Service::STATUS_ARCHIVED, ['slug' => 'archived-service']),
            $this->service(Service::STATUS_PUBLISHED, [
                'slug' => 'future-service',
                'published_at' => now()->addDay(),
            ]),
        ];

        foreach ($public as $service) {
            $this->get(route('services.show', $service->slug))
                ->assertOk()
                ->assertSee($service->name);
        }

        foreach ($hidden as $service) {
            $this->get('/services/'.$service->slug)->assertNotFound();
        }

        $this->get('/services/not-a-service')->assertNotFound();
    }

    public function test_route_is_named_reserved_and_navigation_resolver_only_exposes_public_services(): void
    {
        $route = Route::getRoutes()->getByName('services.show');

        $this->assertNotNull($route);
        $this->assertSame('services/{slug}', $route->uri());
        $this->assertSame(
            ServiceController::class.'@show',
            $route->getActionName(),
        );

        $published = $this->service(Service::STATUS_PUBLISHED, ['slug' => 'navigation-service']);
        $draft = $this->service(Service::STATUS_DRAFT, ['slug' => 'hidden-navigation-service']);

        $this->assertSame('/services/navigation-service', $published->resolveNavigationUrl());
        $this->assertNull($draft->resolveNavigationUrl());
    }

    public function test_production_uses_default_then_specific_template_without_preview_state(): void
    {
        $service = $this->service(Service::STATUS_PUBLISHED, [
            'name' => 'Production Runtime Service',
            'slug' => 'production-runtime-service',
            'overview' => '<p>Production overview marker</p>',
        ]);
        $default = $this->productionTemplate();

        $response = $this->get(route('services.show', $service->slug))
            ->assertOk()
            ->assertSee('Production Runtime Service')
            ->assertSee('Production overview marker', false)
            ->assertDontSee('پیش‌نمایش مدیر');

        $this->assertStringContainsString('معرفی خدمت', $response->getContent());

        $specific = $this->productionTemplate([
            'slug' => 'specific-service-template',
            'is_default' => false,
            'conditions' => ['type' => 'specific_item', 'item_id' => $service->getKey()],
        ]);
        $blocks = $specific->blocks;
        $overview = collect($blocks)->search(fn (array $block): bool => $block['type'] === 'service_overview');
        $blocks[$overview]['data']['content']['title'] = 'Specific Service Template Marker';
        $specific->update(['blocks' => $blocks]);

        $this->get(route('services.show', $service->slug))
            ->assertOk()
            ->assertSee('Specific Service Template Marker')
            ->assertDontSee('معرفی خدمت');

        $this->assertSame('published', $default->fresh()->status);
    }

    public function test_missing_published_template_is_a_controlled_service_unavailable_response(): void
    {
        $service = $this->service(Service::STATUS_PUBLISHED, ['slug' => 'without-template']);

        $this->get(route('services.show', $service->slug))
            ->assertStatus(503)
            ->assertDontSee('No renderable service_single template is available.');
    }

    public function test_production_and_preview_seo_use_public_canonical_with_allowed_robots_difference(): void
    {
        $service = $this->service(Service::STATUS_PUBLISHED, [
            'name' => 'SEO Service',
            'slug' => 'seo-service',
            'excerpt' => 'SEO fallback description.',
            'seo_title' => null,
            'seo_description' => null,
        ]);
        $template = $this->productionTemplate();
        $canonical = url('/services/seo-service');

        $production = $this->get(route('services.show', $service->slug))
            ->assertOk()
            ->assertSee('<title>SEO Service</title>', false)
            ->assertSee('content="SEO fallback description."', false)
            ->assertSee('content="index, follow"', false)
            ->assertSee('href="'.$canonical.'"', false)
            ->assertSee('property="og:title" content="SEO Service"', false)
            ->assertSee('name="twitter:title" content="SEO Service"', false);

        $preview = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.templates.show', [
                'template' => $template,
                'context_id' => $service,
            ]))
            ->assertOk()
            ->assertSee('content="noindex, nofollow"', false)
            ->assertSee('href="'.$canonical.'"', false);

        $this->assertStringNotContainsString('/admin/preview', $production->getContent());
        $this->assertStringNotContainsString('/admin/preview', $preview->getContent());
    }

    public function test_production_template_contract_and_renderer_query_safety(): void
    {
        $service = $this->service(Service::STATUS_PUBLISHED, [
            'benefits' => [['title' => 'Benefit']],
            'process' => [['title' => 'Step']],
            'deliverables' => [['title' => 'Deliverable']],
        ]);
        $template = $this->productionTemplate();

        $this->assertSame('service_single', $template->type);
        $this->assertSame('published', $template->status);
        $this->assertTrue($template->is_default);
        $this->assertSame(['type' => 'all'], $template->conditions);
        $this->assertCount(9, collect($template->blocks)->pluck('data.block_id')->unique());
        $this->assertSame(
            [],
            app(TemplatePublicationValidator::class)->validate($template->toArray()),
        );
        $this->assertSame('/contact', data_get(
            collect($template->blocks)->firstWhere('type', 'cta'),
            'data.content.primary_cta.action.value',
        ));

        $context = app(ServiceTemplateContextBuilder::class)->build($service);
        DB::flushQueryLog();
        DB::enableQueryLog();

        view('partials.page-blocks', [
            'blocks' => $template->blocks,
            'context' => [
                ...$context['templateContext'],
                ...$context,
            ],
        ])->render();

        $this->assertSame([], DB::getQueryLog());
    }

    private function productionTemplate(array $overrides = []): Template
    {
        $template = app(TemplateRecipeInstantiator::class)->createDraft(
            'service-professional-v1',
            [
                'title' => 'Service Professional v1',
                'slug' => 'service-professional-v1-'.uniqid(),
                'is_default' => true,
                'conditions' => ['type' => 'all'],
                ...$overrides,
            ],
        );
        $blocks = $template->blocks;
        $ctaIndex = collect($blocks)->search(fn (array $block): bool => $block['type'] === 'cta');
        $cta = $blocks[$ctaIndex]['data'];
        $cta['content']['title'] = 'برای شروع آماده‌اید؟';
        $cta['content']['description'] = 'برای بررسی نیاز پروژه با ما در تماس باشید.';
        $cta['content']['primary_cta'] = [
            'label' => 'درخواست مشاوره',
            'action' => [
                'type' => 'url',
                'url' => '/contact',
                'form_id' => null,
                'display' => null,
            ],
        ];
        $blocks[$ctaIndex]['data'] = app(CTADataNormalizer::class)->normalize($cta);
        $template->update([
            'blocks' => $blocks,
            'status' => 'published',
        ]);

        return $template->fresh();
    }

    private function service(string $status, array $overrides = []): Service
    {
        return Service::query()->create([
            'name' => 'Service '.uniqid(),
            'slug' => 'service-'.uniqid(),
            'excerpt' => 'Service excerpt.',
            'overview' => '<p>Service overview.</p>',
            'status' => $status,
            ...$overrides,
        ]);
    }
}
