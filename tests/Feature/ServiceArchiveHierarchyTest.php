<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\Template;
use App\Services\ServiceArchiveContextBuilder;
use App\Services\ServiceQueryService;
use Database\Seeders\ServiceArchiveTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceArchiveHierarchyTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_archive_groups_public_roots_and_direct_children_in_sibling_order(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);

        $secondRoot = $this->service('مدیریت پروژه', 'project-management', 20);
        $firstRoot = $this->service('خدمات طراحی', 'design-services', 10, excerpt: 'معرفی گروه طراحی');
        $this->service('طراحی تأسیسات', 'installation-design', 30, $firstRoot);
        $this->service('طراحی معماری', 'architecture-design', 10, $firstRoot);
        $middle = $this->service('طراحی سازه', 'structure-design', 20, $firstRoot);
        $this->service('زیرمجموعه سطح سوم', 'third-level-service', 1, $middle);
        $this->service('کنترل پروژه', 'project-control', 20, $secondRoot);
        $this->service('متره و برآورد', 'quantity-surveying', 10, $secondRoot);
        $this->service('فرزند منتشرنشده', 'draft-child', 1, $firstRoot, Service::STATUS_DRAFT);
        $hiddenRoot = $this->service('گروه منتشرنشده', 'draft-root', 1, status: Service::STATUS_DRAFT);
        $this->service('فرزند گروه منتشرنشده', 'hidden-root-child', 1, $hiddenRoot);

        $response = $this->get(route('services.index'))->assertOk();

        $response
            ->assertSeeInOrder(['خدمات طراحی', 'طراحی معماری', 'طراحی سازه', 'طراحی تأسیسات', 'مدیریت پروژه', 'متره و برآورد', 'کنترل پروژه'])
            ->assertSee('معرفی گروه طراحی')
            ->assertDontSee('زیرمجموعه سطح سوم')
            ->assertDontSee('فرزند منتشرنشده')
            ->assertDontSee('گروه منتشرنشده')
            ->assertDontSee('فرزند گروه منتشرنشده')
            ->assertSee('template-content-grid', false)
            ->assertSee('service-archive-groups', false);
    }

    public function test_root_without_children_uses_its_own_standard_card_for_legacy_flat_services(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);
        $this->service('خدمت قدیمی مستقل', 'legacy-flat-service', 1, excerpt: 'توضیح خدمت مستقل');

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('service-archive-group--root-fallback', false)
            ->assertSee('class="shared-collection-card"', false)
            ->assertSee(route('services.show', 'legacy-flat-service', absolute: false), false);
    }

    public function test_fallback_archive_reuses_the_same_grouped_presentation(): void
    {
        $this->seed(ServiceArchiveTemplateSeeder::class);
        Template::query()->where('type', 'service_index')->update(['status' => 'draft']);
        $root = $this->service('گروه مسیر جایگزین', 'fallback-root', 1);
        $this->service('فرزند مسیر جایگزین', 'fallback-child', 1, $root);

        $this->get(route('services.index'))
            ->assertOk()
            ->assertSee('class="services-archive"', false)
            ->assertSee('service-archive-groups', false)
            ->assertSee('گروه مسیر جایگزین')
            ->assertSee('فرزند مسیر جایگزین')
            ->assertDontSee('archive-header--modern', false);
    }

    public function test_archive_context_paginates_root_groups_without_changing_flat_query_contract(): void
    {
        foreach (range(1, 13) as $index) {
            $this->service('Root '.str_pad((string) $index, 2, '0', STR_PAD_LEFT), 'root-'.$index, $index);
        }

        $queries = app(ServiceQueryService::class);
        $roots = $queries->paginatePublicArchiveRoots(12);
        $archive = app(ServiceArchiveContextBuilder::class)->build(
            $roots,
            $queries->publicArchiveChildren($roots->getCollection()),
            'خدمات',
            null,
        );

        $this->assertCount(12, $archive->groups);
        $this->assertNotNull($archive->groups[11]->collection->pagination);
        $this->assertSame(13, $queries->paginateArchive(48)->total());

        $this->get(route('services.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Root 13')
            ->assertDontSee('Root 01');
    }

    private function service(
        string $name,
        string $slug,
        int $sortOrder,
        ?Service $parent = null,
        string $status = Service::STATUS_PUBLISHED,
        ?string $excerpt = null,
    ): Service {
        return Service::query()->create([
            'name' => $name,
            'slug' => $slug,
            'sort_order' => $sortOrder,
            'parent_id' => $parent?->getKey(),
            'status' => $status,
            'excerpt' => $excerpt,
        ]);
    }
}
