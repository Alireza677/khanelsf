<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectCategory;
use App\Services\ModuleService;
use App\Services\ProjectTemplateContextBuilder;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\TemplateService;
use Illuminate\Contracts\View\View;

class ProjectController extends Controller
{
    public function index(SeoService $seoService, SettingsService $settings, ModuleService $modules, TemplateService $templates): View
    {
        $this->abortIfProjectsDisabled($modules);

        $projects = Project::query()
            ->with(['category', 'media'])
            ->published()
            ->orderBy('sort_order')
            ->latest('published_at')
            ->paginate((int) $settings->get('projects_per_page', 12));

        $categories = ProjectCategory::query()
            ->active()
            ->withCount(['projects' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $template = $templates->findTemplateFor('projects_index');

        return $templates->viewOrFallback($template, 'projects.index', [
            'projects' => $projects,
            'categories' => $categories,
            'heading' => $settings->get('projects_index_title', 'Projects'),
            'description' => $settings->get('projects_index_description', 'Selected work and case studies.'),
            'seo' => $seoService->forProjectIndex(),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'projects',
                'items' => $projects,
                'categories' => $categories,
                'heading' => $settings->get('projects_index_title', 'Projects'),
                'description' => $settings->get('projects_index_description', 'Selected work and case studies.'),
                'emptyMessage' => 'No projects have been published yet.',
            ],
        ]);
    }

    public function category(string $slug, SeoService $seoService, SettingsService $settings, ModuleService $modules, TemplateService $templates): View
    {
        $this->abortIfProjectsDisabled($modules);

        $category = ProjectCategory::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        $projects = Project::query()
            ->with(['category', 'media'])
            ->published()
            ->whereBelongsTo($category, 'category')
            ->orderBy('sort_order')
            ->latest('published_at')
            ->paginate((int) $settings->get('projects_per_page', 12));

        $template = $templates->findTemplateFor('project_category', $category);

        return $templates->viewOrFallback($template, 'projects.index', [
            'projects' => $projects,
            'categories' => collect([$category]),
            'heading' => $category->name,
            'description' => $category->description,
            'activeCategory' => $category,
            'emptyMessage' => 'No projects have been published in this category yet.',
            'seo' => $seoService->forProjectCategory($category),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'projects',
                'items' => $projects,
                'categories' => collect([$category]),
                'category' => $category,
                'activeCategory' => $category,
                'heading' => $category->name,
                'description' => $category->description,
                'emptyMessage' => 'No projects have been published in this category yet.',
            ],
        ]);
    }

    public function show(
        string $slug,
        SeoService $seoService,
        SettingsService $settings,
        ModuleService $modules,
        TemplateService $templates,
        ProjectTemplateContextBuilder $contextBuilder,
    ): View {
        $this->abortIfProjectsDisabled($modules);

        $project = Project::query()
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        $template = $templates->findTemplateFor('project_single', $project);
        $context = $contextBuilder->build($project, $template, $modules->galleriesEnabled());

        return $templates->viewOrFallback($template, 'projects.show', [
            ...$context,
            'seo' => $seoService->forProject($project),
        ]);
    }

    private function abortIfProjectsDisabled(ModuleService $modules): void
    {
        abort_unless($modules->projectsEnabled(), 404);
    }
}
