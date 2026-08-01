<?php

namespace Tests\Feature;

use App\CMS\Templates\Recipes\TemplateRecipeInstantiator;
use App\Filament\Resources\TemplateResource;
use App\Filament\Resources\TemplateResource\Pages\EditTemplate;
use App\Filament\Resources\TemplateResource\Pages\ListTemplates;
use App\Models\Service;
use App\Models\Template;
use App\Models\User;
use App\Services\ServiceTemplateContextBuilder;
use App\Services\ServiceTemplateRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceRecipePreviewIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_recipe_action_creates_an_independent_service_draft_without_domain_records(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $existing = Template::query()->create([
            'title' => 'Existing published template',
            'slug' => 'existing-published-template',
            'type' => 'service_single',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [['type' => 'service_header', 'data' => []]],
        ]);

        Livewire::test(ListTemplates::class)
            ->assertActionExists('createFromRecipe')
            ->callAction('createFromRecipe', data: [
                'recipe' => 'service-professional-v1',
                'title' => 'Service Professional v1',
                'slug' => 'service-professional-v1',
                'is_default' => false,
            ])
            ->assertHasNoActionErrors();

        $draft = Template::query()->where('slug', 'service-professional-v1')->firstOrFail();

        $this->assertSame('draft', $draft->status);
        $this->assertSame('service_single', $draft->type);
        $this->assertFalse($draft->is_default);
        $this->assertSame(['type' => 'all'], $draft->conditions);
        $this->assertCount(9, collect($draft->blocks)->pluck('data.block_id')->unique());
        $this->assertSame(0, Service::query()->count());
        $this->assertSame('published', $existing->fresh()->status);
        $this->assertSame('Existing published template', $existing->fresh()->title);
    }

    public function test_admin_can_preview_all_service_lifecycle_states_through_the_same_runtime(): void
    {
        $admin = User::factory()->admin()->create();
        $template = $this->recipeDraft();
        $services = collect([
            Service::STATUS_ACTIVE,
            Service::STATUS_PUBLISHED,
            Service::STATUS_DRAFT,
            Service::STATUS_INACTIVE,
            Service::STATUS_ARCHIVED,
        ])->map(fn (string $status): Service => $this->service($status));
        $previewOptions = TemplateResource::previewContextOptions('service_single');

        $this->assertSame('Preview service', TemplateResource::previewContextLabel('service_single'));

        foreach ($services as $service) {
            $this->assertSame($service->name, $previewOptions[$service->getKey()]);
        }

        foreach ($services as $service) {
            $response = $this->actingAs($admin)
                ->get(route('admin.preview.templates.show', [
                    'template' => $template,
                    'context_id' => $service,
                ]))
                ->assertOk()
                ->assertSee($service->name)
                ->assertSee('content="noindex, nofollow"', false);

            $this->assertStringContainsString(
                '<link rel="canonical" href="http://localhost/services/'.$service->slug.'">',
                $response->getContent(),
            );
            $this->assertStringNotContainsString('/admin/preview', $response->getContent());
        }

        $this->assertArrayNotHasKey('context_id', $template->fresh()->getAttributes());
        $this->assertStringNotContainsString(
            'context_id',
            json_encode($template->fresh()->blocks, JSON_THROW_ON_ERROR),
        );
    }

    public function test_preview_permissions_and_missing_service_are_managed(): void
    {
        $template = $this->recipeDraft();

        $this->get(route('admin.preview.templates.show', $template))
            ->assertRedirect('/admin/login');

        $this->actingAs(User::factory()->create())
            ->get(route('admin.preview.templates.show', $template))
            ->assertForbidden();

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.preview.templates.show', $template))
            ->assertOk()
            ->assertSee('ابتدا یک خدمت را انتخاب کنید')
            ->assertSee('content="noindex, nofollow"', false);

        $this->assertStringNotContainsString('<link rel="canonical"', $response->getContent());
    }

    public function test_preview_and_future_production_use_runtime_context_and_registered_renderers_without_queries(): void
    {
        $admin = User::factory()->admin()->create();
        $service = $this->service(Service::STATUS_PUBLISHED, [
            'name' => 'Parity Service',
            'overview' => '<p>Parity Overview</p>',
            'benefits' => [['title' => 'Parity Benefit']],
            'process' => [['title' => 'Parity Step']],
            'deliverables' => [['title' => 'Parity Deliverable']],
        ]);
        $template = $this->recipeDraft();
        $runtime = app(ServiceTemplateRuntime::class);

        $production = $runtime->render($service, template: $template)->render();
        $preview = $this->actingAs($admin)
            ->get(route('admin.preview.templates.show', [
                'template' => $template,
                'context_id' => $service,
            ]))
            ->assertOk()
            ->getContent();

        foreach (['Parity Service', 'Parity Overview', 'Parity Benefit', 'Parity Step', 'Parity Deliverable'] as $marker) {
            $this->assertStringContainsString($marker, $production);
            $this->assertStringContainsString($marker, $preview);
        }

        $this->assertStringContainsString('content="index, follow"', $production);
        $this->assertStringContainsString('content="noindex, nofollow"', $preview);

        $context = app(ServiceTemplateContextBuilder::class)->build($service);
        DB::flushQueryLog();
        DB::enableQueryLog();

        foreach ($template->blocks as $block) {
            view('partials.page-blocks', [
                'blocks' => [$block],
                'context' => [
                    ...$context['templateContext'],
                    ...$context,
                ],
            ])->render();
        }

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_service_runtime_rejects_a_product_or_project_template(): void
    {
        $service = $this->service(Service::STATUS_DRAFT);

        foreach (['product_single', 'project_single'] as $type) {
            $template = Template::query()->create([
                'title' => $type,
                'slug' => $type.'-'.uniqid(),
                'type' => $type,
                'status' => 'draft',
                'blocks' => [['type' => 'service_header', 'data' => []]],
                'is_default' => false,
                'conditions' => ['type' => 'all'],
            ]);

            try {
                app(ServiceTemplateRuntime::class)->render($service, preview: true, template: $template);
                $this->fail("Template [{$type}] must be rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_service_template_cannot_be_published_empty_or_with_incompatible_blocks(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $empty = Template::query()->create([
            'title' => 'Empty service template',
            'slug' => 'empty-service-template',
            'type' => 'service_single',
            'status' => 'draft',
            'blocks' => [],
            'is_default' => false,
            'conditions' => ['type' => 'all'],
        ]);

        Livewire::test(EditTemplate::class, ['record' => $empty->getRouteKey()])
            ->set('data.status', 'published')
            ->call('save')
            ->assertHasFormErrors(['blocks']);

        $this->assertSame('draft', $empty->fresh()->status);
    }

    private function recipeDraft(): Template
    {
        return app(TemplateRecipeInstantiator::class)->createDraft('service-professional-v1', [
            'title' => 'Service Professional v1',
            'slug' => 'service-professional-v1-'.uniqid(),
            'conditions' => ['type' => 'all'],
        ]);
    }

    private function service(string $status, array $overrides = []): Service
    {
        return Service::query()->create([
            'name' => 'Preview '.$status.' '.uniqid(),
            'slug' => 'preview-'.$status.'-'.uniqid(),
            'status' => $status,
            ...$overrides,
        ]);
    }
}
