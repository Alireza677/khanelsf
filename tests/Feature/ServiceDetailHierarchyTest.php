<?php

namespace Tests\Feature;

use App\CMS\Collections\Data\CollectionPresentation;
use App\Models\Service;
use App\Models\Template;
use App\Services\ServiceTemplateContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceDetailHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_parent_detail_exposes_only_direct_public_children_in_stable_order(): void
    {
        $this->template();
        $root = $this->service('خدمات طراحی', 'design-services', 1);
        $third = $this->service('طراحی تأسیسات', 'installation-design', 30, $root);
        $first = $this->service('طراحی معماری', 'architecture-design', 10, $root);
        $second = $this->service('طراحی سازه', 'structure-design', 20, $root);
        $this->service('نوه خدمت', 'service-grandchild', 1, $first);
        $this->service('فرزند پیش‌نویس', 'draft-child', 1, $root, Service::STATUS_DRAFT);

        $context = app(ServiceTemplateContextBuilder::class)->build($root);

        $this->assertInstanceOf(CollectionPresentation::class, $context['relatedServices']);
        $this->assertSame(
            [$first->name, $second->name, $third->name],
            collect($context['relatedServices']->items)->pluck('title')->all(),
        );

        $html = view('partials.blocks.related_services', [
            'data' => [],
            'context' => $context,
        ])->render();

        $this->assertStringContainsString('shared-collection__grid--3', $html);
        $this->assertStringContainsString('طراحی معماری', $html);
        $this->assertStringNotContainsString('نوه خدمت', $html);
        $this->assertStringNotContainsString('فرزند پیش‌نویس', $html);

        $this->get(route('services.show', $root->slug))
            ->assertOk()
            ->assertSeeInOrder(['طراحی معماری', 'طراحی سازه', 'طراحی تأسیسات'])
            ->assertDontSee('نوه خدمت')
            ->assertDontSee('فرزند پیش‌نویس');
    }

    public function test_root_child_and_grandchild_use_one_visual_and_json_ld_ancestor_chain(): void
    {
        $this->template();
        $root = $this->service('گروه اصلی', 'root-service', 1);
        $child = $this->service('خدمت فرزند', 'child-service', 1, $root);
        $grandchild = $this->service('خدمت نوه', 'grandchild-service', 1, $child);

        $rootContext = app(ServiceTemplateContextBuilder::class)->build($root);
        $childContext = app(ServiceTemplateContextBuilder::class)->build($child);
        $grandchildContext = app(ServiceTemplateContextBuilder::class)->build($grandchild);

        $this->assertSame(['خانه', 'خدمات', 'گروه اصلی'], collect($rootContext['breadcrumbs'])->pluck('name')->all());
        $this->assertSame(['خانه', 'خدمات', 'گروه اصلی', 'خدمت فرزند'], collect($childContext['breadcrumbs'])->pluck('name')->all());
        $this->assertSame(
            ['خانه', 'خدمات', 'گروه اصلی', 'خدمت فرزند', 'خدمت نوه'],
            collect($grandchildContext['breadcrumbs'])->pluck('name')->all(),
        );

        $breadcrumbSchema = collect($grandchildContext['seo']->schema['@graph'])->firstWhere('@type', 'BreadcrumbList');
        $this->assertSame(
            collect($grandchildContext['breadcrumbs'])->pluck('url')->all(),
            collect($breadcrumbSchema['itemListElement'])->pluck('item')->all(),
        );

        $this->get(route('services.show', $grandchild->slug))
            ->assertOk()
            ->assertSeeInOrder(['خانه', 'خدمات', 'گروه اصلی', 'خدمت فرزند', 'خدمت نوه'])
            ->assertSee('service-breadcrumb', false)
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_non_public_ancestor_is_not_disclosed_in_visual_or_structured_breadcrumbs(): void
    {
        $publicRoot = $this->service('والد عمومی دور', 'public-distant-parent', 1);
        $privateParent = $this->service('والد خصوصی محرمانه', 'private-parent', 1, $publicRoot, Service::STATUS_DRAFT);
        $service = $this->service('خدمت عمومی مستقل', 'public-descendant', 1, $privateParent);

        $context = app(ServiceTemplateContextBuilder::class)->build($service);
        $this->assertSame(
            ['خانه', 'خدمات', 'خدمت عمومی مستقل'],
            collect($context['breadcrumbs'])->pluck('name')->all(),
        );

        $breadcrumbSchema = collect($context['seo']->schema['@graph'])->firstWhere('@type', 'BreadcrumbList');
        $schemaNames = collect($breadcrumbSchema['itemListElement'])->pluck('name');
        $this->assertFalse($schemaNames->contains('والد خصوصی محرمانه'));
        $this->assertFalse($schemaNames->contains('والد عمومی دور'));

        $header = view('partials.blocks.service_header', ['data' => [], 'context' => $context])->render();
        $this->assertStringNotContainsString('والد خصوصی محرمانه', $header);
        $this->assertStringNotContainsString('والد عمومی دور', $header);
    }

    public function test_service_without_public_children_does_not_render_related_services_space(): void
    {
        $service = $this->service('خدمت بدون زیرمجموعه', 'leaf-service', 1);
        $context = app(ServiceTemplateContextBuilder::class)->build($service);

        $this->assertSame([], $context['relatedServices']->items);
        $this->assertSame('', trim(view('partials.blocks.related_services', [
            'data' => [],
            'context' => $context,
        ])->render()));
    }

    private function service(
        string $name,
        string $slug,
        int $sortOrder,
        ?Service $parent = null,
        string $status = Service::STATUS_PUBLISHED,
    ): Service {
        return Service::query()->create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'parent_id' => $parent?->getKey(),
            'status' => $status,
        ]);
    }

    private function template(): Template
    {
        return Template::query()->create([
            'title' => 'قالب جزئیات سلسله‌مراتبی',
            'slug' => 'service-detail-hierarchy',
            'type' => 'service_single',
            'status' => 'published',
            'is_default' => true,
            'conditions' => ['type' => 'all'],
            'blocks' => [
                ['type' => 'service_header', 'data' => []],
                ['type' => 'related_services', 'data' => []],
            ],
        ]);
    }
}
