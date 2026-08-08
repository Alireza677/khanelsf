<?php

namespace App\Http\Controllers;

use App\Services\ModuleService;
use App\Services\ProjectGalleryDiscoveryService;
use App\Services\ProjectDiscoveryTemplateContextBuilder;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\TemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index(
        Request $request,
        SeoService $seoService,
        SettingsService $settings,
        ModuleService $modules,
        ProjectGalleryDiscoveryService $discovery,
        ProjectDiscoveryTemplateContextBuilder $contextBuilder,
        TemplateService $templates,
    ): View
    {
        $this->abortIfGalleriesDisabled($modules);
        abort_unless($modules->projectsEnabled(), 404);

        $result = $discovery->discover(
            is_array($request->query('filters')) ? $request->query('filters') : [],
            (int) $settings->get('galleries_per_page', 12),
        );

        $heading = $settings->get('galleries_index_title', 'Galleries');
        $description = $settings->get('galleries_index_description', 'Browse image and video galleries.');
        $template = $templates->findTemplateFor('project_discovery_index');
        $templateContext = $contextBuilder->build($result, $heading, $description);

        return $templates->viewOrFallback($template, 'galleries.discovery', [
            ...$result,
            'heading' => $heading,
            'description' => $description,
            'seo' => $seoService->forGalleryIndex(),
            'templateContext' => $templateContext,
        ]);
    }

    public function category(string $slug): never
    {
        abort(404);
    }

    public function show(string $slug): never
    {
        abort(404);
    }

    private function abortIfGalleriesDisabled(ModuleService $modules): void
    {
        abort_unless($modules->galleriesEnabled(), 404);
    }
}
