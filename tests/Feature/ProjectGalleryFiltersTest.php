<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Service;
use App\Services\ProjectGalleryFilterService;
use Database\Seeders\ProjectArchiveTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGalleryFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_count_uses_real_fields_and_relations_with_or_within_groups_and_and_between_them(): void
    {
        $filters = app(ProjectGalleryFilterService::class);
        $lsf = Service::create(['name' => 'LSF', 'slug' => 'lsf', 'status' => 'active']);
        $concrete = Service::create(['name' => 'Concrete', 'slug' => 'concrete', 'status' => 'active']);
        foreach ([
            ['Residential', 'Tehran', '2025-03-21', $lsf],
            ['Commercial', 'Kerman', '2025-03-20', $concrete],
            ['Residential', 'Kerman', '2025-04-01', $lsf],
        ] as [$type, $location, $date, $service]) {
            Project::factory()->published()->create([
                'project_type' => $type, 'location' => $location, 'project_date' => $date,
            ])->relatedServices()->attach($service);
        }
        Project::factory()->draft()->create();
        Project::factory()->published()->create(['published_at' => now()->addDay()]);

        $count = fn (array $query) => $this->withHeaders(['X-Project-Gallery' => 'count'])
            ->getJson(route('galleries.index').'?'.http_build_query($query));
        $count([])->assertOk()->assertExactJson(['count' => 3]);
        $selected = ['type' => [$filters->textId('Residential'), $filters->textId('Commercial')]];
        $count($selected)->assertExactJson(['count' => 3]);
        $selected['location'] = [$filters->textId('Kerman')];
        $count($selected)->assertExactJson(['count' => 2]);
        $selected['service'] = [(string) $lsf->id];
        $count($selected)->assertExactJson(['count' => 1]);
        $selected['year'] = ['1403'];
        $count($selected)->assertExactJson(['count' => 0]);
        $selected['year'] = ['1403', '1404'];
        $count($selected)->assertExactJson(['count' => 1]);
        $count(['service' => [(string) $lsf->id, (string) $concrete->id]])->assertExactJson(['count' => 3]);
        $count(['year' => ['1403']])->assertExactJson(['count' => 1]);
        $count(['year' => ['1404']])->assertExactJson(['count' => 2]);
        $count(['location' => [$filters->textId('Missing')]])->assertExactJson(['count' => 0]);
    }

    public function test_filtered_template_keeps_pagination_and_restarts_the_card_pattern_on_each_page(): void
    {
        $this->seed(ProjectArchiveTemplateSeeder::class);
        Project::factory()->published()->count(13)->create(['project_type' => 'Residential']);
        $type = app(ProjectGalleryFilterService::class)->textId('Residential');
        $url = route('galleries.index').'?'.http_build_query(['type' => [$type]]);

        $first = $this->get($url)->assertOk()
            ->assertSee('data-project-gallery-results', false)
            ->assertSee('data-count="13"', false)
            ->assertSee('type%5B0%5D='.$type, false);
        $this->assertSame(12, substr_count($first->getContent(), 'shared-collection-card--masonry'));
        $second = $this->get($url.'&page=2')->assertOk();
        $this->assertSame(1, substr_count($second->getContent(), 'shared-collection-card--masonry'));
        $second->assertDontSee('shared-collection-card--desktop-column-start');
        $this->get(route('galleries.index').'?year[]=1400')->assertOk()
            ->assertSee('پروژه‌ای با این فیلترها پیدا نشد.')
            ->assertSee('data-project-filter-reset', false);
    }
}
