<?php

namespace Tests\Feature;

use App\CMS\Navigation\NavigationSourceRegistry;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Template;
use Database\Seeders\ProjectArchiveTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectGalleryConvergenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_galleries_is_the_canonical_project_archive(): void
    {
        $this->seed(ProjectArchiveTemplateSeeder::class);
        $project = Project::factory()->published()->create([
            'title' => 'پروژه همگرا',
            'slug' => 'converged-project',
        ]);

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertSee('shared-collection--masonry_gallery', false)
            ->assertSee('پروژه همگرا')
            ->assertSee(route('projects.show', $project->slug, absolute: false), false)
            ->assertSee('<link rel="canonical" href="'.route('galleries.index').'">', false)
            ->assertDontSee('Browse image and video galleries.');

        $this->get(route('projects.show', $project->slug))->assertOk();
    }

    public function test_projects_index_redirects_permanently_and_preserves_query_string(): void
    {
        $this->get('/projects?page=2&filter=featured')
            ->assertStatus(301)
            ->assertRedirect(route('galleries.index').'?filter=featured&page=2');
    }

    public function test_missing_or_draft_template_falls_back_without_returning_to_legacy_gallery(): void
    {
        Project::factory()->published()->create(['title' => 'پروژه fallback']);

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertSee('پروژه fallback')
            ->assertDontSee('Browse image and video galleries.');

        $this->seed(ProjectArchiveTemplateSeeder::class);
        Template::query()->where('slug', ProjectArchiveTemplateSeeder::TEMPLATE_SLUG)->update([
            'status' => 'draft',
        ]);

        $this->get(route('galleries.index'))
            ->assertOk()
            ->assertSee('پروژه fallback')
            ->assertDontSee('Browse image and video galleries.');
    }

    public function test_navigation_and_sitemap_use_only_the_project_owned_canonical_archive(): void
    {
        $source = app(NavigationSourceRegistry::class)->find('galleries.archive');

        $this->assertNotNull($source);
        $this->assertSame('projects', $source->module);
        $this->assertSame('/galleries', $source->resolve());

        $sitemap = $this->get(route('sitemap'))->assertOk()->getContent();
        $this->assertStringContainsString(route('galleries.index'), $sitemap);
        $this->assertStringNotContainsString('<loc>'.route('projects.index').'</loc>', $sitemap);

        Setting::query()->updateOrCreate(['key' => 'galleries_enabled'], [
            'value' => '0', 'group' => 'galleries', 'type' => 'boolean',
        ]);
        $this->get(route('galleries.index'))->assertOk();
        $this->assertTrue($source->isAvailable());

        Setting::query()->updateOrCreate(['key' => 'projects_enabled'], [
            'value' => '0', 'group' => 'projects', 'type' => 'boolean',
        ]);
        $this->get(route('galleries.index'))->assertNotFound();
        $this->assertFalse($source->isAvailable());
    }
}
