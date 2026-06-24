<?php

namespace App\Http\Controllers;

use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Services\ModuleService;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\TemplateService;
use Illuminate\Contracts\View\View;

class GalleryController extends Controller
{
    public function index(SeoService $seoService, SettingsService $settings, ModuleService $modules, TemplateService $templates): View
    {
        $this->abortIfGalleriesDisabled($modules);

        $galleries = Gallery::query()
            ->with(['category', 'project'])
            ->published()
            ->withPublicCategory()
            ->orderBy('sort_order')
            ->latest('published_at')
            ->paginate((int) $settings->get('galleries_per_page', 12));

        $categories = GalleryCategory::query()
            ->active()
            ->withCount(['galleries' => fn ($query) => $query->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $template = $templates->findTemplateFor('galleries_index');

        return $templates->viewOrFallback($template, 'galleries.index', [
            'galleries' => $galleries,
            'categories' => $categories,
            'heading' => $settings->get('galleries_index_title', 'Galleries'),
            'description' => $settings->get('galleries_index_description', 'Browse image and video galleries.'),
            'seo' => $seoService->forGalleryIndex(),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'galleries',
                'items' => $galleries,
                'categories' => $categories,
                'heading' => $settings->get('galleries_index_title', 'Galleries'),
                'description' => $settings->get('galleries_index_description', 'Browse image and video galleries.'),
                'emptyMessage' => 'No galleries have been published yet.',
            ],
        ]);
    }

    public function category(string $slug, SeoService $seoService, SettingsService $settings, ModuleService $modules, TemplateService $templates): View
    {
        $this->abortIfGalleriesDisabled($modules);

        $category = GalleryCategory::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        $galleries = Gallery::query()
            ->with(['category', 'project'])
            ->published()
            ->withPublicCategory()
            ->whereBelongsTo($category, 'category')
            ->orderBy('sort_order')
            ->latest('published_at')
            ->paginate((int) $settings->get('galleries_per_page', 12));

        $template = $templates->findTemplateFor('gallery_category', $category);

        return $templates->viewOrFallback($template, 'galleries.category', [
            'galleries' => $galleries,
            'categories' => collect([$category]),
            'heading' => $category->name,
            'description' => $category->description,
            'activeCategory' => $category,
            'emptyMessage' => 'No galleries have been published in this category yet.',
            'seo' => $seoService->forGalleryCategory($category),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'galleries',
                'items' => $galleries,
                'categories' => collect([$category]),
                'category' => $category,
                'activeCategory' => $category,
                'heading' => $category->name,
                'description' => $category->description,
                'emptyMessage' => 'No galleries have been published in this category yet.',
            ],
        ]);
    }

    public function show(string $slug, SeoService $seoService, ModuleService $modules, TemplateService $templates): View
    {
        $this->abortIfGalleriesDisabled($modules);

        $gallery = Gallery::query()
            ->with(['category', 'project'])
            ->where('slug', $slug)
            ->published()
            ->withPublicCategory()
            ->firstOrFail();

        $relatedGalleries = Gallery::query()
            ->with('category')
            ->published()
            ->withPublicCategory()
            ->whereKeyNot($gallery->getKey())
            ->when($gallery->gallery_category_id, fn ($query) => $query->where('gallery_category_id', $gallery->gallery_category_id))
            ->orderBy('sort_order')
            ->latest('published_at')
            ->take(3)
            ->get();

        $template = $templates->findTemplateFor('gallery_single', $gallery);

        return $templates->viewOrFallback($template, 'galleries.show', [
            'gallery' => $gallery,
            'relatedGalleries' => $relatedGalleries,
            'seo' => $seoService->forGallery($gallery),
            'templateContext' => [
                'kind' => 'single',
                'type' => 'gallery',
                'model' => $gallery,
                'related' => $relatedGalleries,
            ],
        ]);
    }

    private function abortIfGalleriesDisabled(ModuleService $modules): void
    {
        abort_unless($modules->galleriesEnabled(), 404);
    }
}
