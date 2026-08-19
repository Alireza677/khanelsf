<?php

namespace Tests\Feature;

use App\CMS\Collections\Data\CollectionItem;
use App\CMS\Collections\Project\ProjectCollectionAdapter;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectCollectionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_adapter_maps_real_project_fields_without_queries_or_project_specific_dto(): void
    {
        $category = ProjectCategory::factory()->create(['name' => 'وب‌سایت']);
        $project = Project::factory()->published()->for($category, 'category')->create([
            'title' => 'پروژه نمونه',
            'slug' => 'sample-project',
            'excerpt' => 'خلاصه پروژه',
            'location' => 'تهران',
            'project_type' => 'طراحی و توسعه',
            'project_date' => '2026-08-01',
        ]);
        $project->media()->create([
            'collection_name' => 'featured_image', 'name' => 'cover', 'file_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg', 'disk' => 'public', 'conversions_disk' => 'public', 'size' => 1,
            'manipulations' => [], 'custom_properties' => [], 'generated_conversions' => [],
            'responsive_images' => [], 'order_column' => 1,
        ]);
        $project->load(['category', 'media']);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $item = app(ProjectCollectionAdapter::class)->item($project);

        $this->assertSame([], DB::getQueryLog());
        $this->assertInstanceOf(CollectionItem::class, $item);
        $this->assertNotInstanceOf(Project::class, $item);
        $this->assertSame('پروژه نمونه', $item->title);
        $this->assertSame('خلاصه پروژه', $item->excerpt);
        $this->assertSame(['وب‌سایت'], $item->badges);
        $this->assertSame(['موقعیت', 'نوع پروژه', 'تاریخ'], array_map(fn ($meta) => $meta->label, $item->metaItems));
        $this->assertSame(route('projects.show', 'sample-project', absolute: false), $item->action?->href);
        $this->assertStringContainsString('cover.jpg', $item->image?->url ?? '');
    }

    public function test_archive_uses_shared_collection_and_preserves_lifecycle_and_category_navigation(): void
    {
        $category = ProjectCategory::factory()->create(['name' => 'مطالعات موردی', 'slug' => 'case-studies']);
        Project::factory()->published()->for($category, 'category')->create(['title' => 'پروژه عمومی']);
        Project::factory()->draft()->for($category, 'category')->create(['title' => 'پروژه پیش‌نویس']);
        Project::factory()->published()->for($category, 'category')->create([
            'title' => 'پروژه آینده', 'published_at' => now()->addDay(),
        ]);

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertSee('shared-collection shared-collection--masonry_gallery', false)
            ->assertSee('shared-collection-card', false)
            ->assertSee('پروژه عمومی')
            ->assertSee(route('projects.category', $category->slug, absolute: false), false)
            ->assertDontSee('پروژه پیش‌نویس')
            ->assertDontSee('پروژه آینده')
            ->assertDontSee('projects.partials.card', false);
    }

    public function test_shared_grid_handles_zero_one_two_three_and_five_projects_with_nullable_fields(): void
    {
        foreach ([0, 1, 2, 3, 5] as $count) {
            $projects = collect($count > 0 ? range(1, $count) : [])->map(function (int $index): Project {
                $project = new Project([
                    'title' => $index === 1 ? str_repeat('عنوان طولانی ', 20) : "Project {$index}",
                    'slug' => "project-{$index}", 'excerpt' => $index % 2 === 0 ? null : 'Excerpt',
                    'location' => null, 'project_type' => null, 'project_date' => null,
                ]);
                $project->setRelation('category', null);
                $project->setRelation('media', collect());

                return $project;
            });
            $paginator = new LengthAwarePaginator($projects, $count, 12, 1, ['path' => '/projects']);
            $collection = app(ProjectCollectionAdapter::class)->adapt($paginator, 'پروژه‌ها');
            $html = view('partials.presentations.collection', compact('collection'))->render();

            $this->assertSame($count, substr_count($html, 'shared-collection-card--masonry'));
            $this->assertStringNotContainsString('App\\Models\\Project', $html);
            if ($count === 0) {
                $this->assertStringContainsString('shared-collection__empty', $html);
                $this->assertStringNotContainsString('shared-collection__grid--3', $html);
            } else {
                $this->assertStringContainsString('shared-collection__grid--3', $html);
                $this->assertStringNotContainsString('shared-collection__empty', $html);
            }
        }
    }

    public function test_pagination_and_module_toggle_remain_domain_owned(): void
    {
        $projects = collect(range(1, 12))->map(function (int $index): Project {
            $project = new Project(['title' => "Project {$index}", 'slug' => "project-{$index}"]);
            $project->setRelation('category', null);
            $project->setRelation('media', collect());

            return $project;
        });
        $paginator = new LengthAwarePaginator($projects, 13, 12, 1, ['path' => '/projects']);
        $collection = app(ProjectCollectionAdapter::class)->adapt($paginator, 'پروژه‌ها');

        $this->assertSame(2, $collection->pagination?->lastPage);
        $this->assertSame('/projects?page=2', $collection->pagination?->nextUrl);

        Setting::query()->updateOrCreate(['key' => 'projects_enabled'], [
            'value' => '0', 'group' => 'projects', 'type' => 'boolean',
        ]);
        $this->get(route('galleries.index'))->assertNotFound();
    }
}
