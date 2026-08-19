<?php

namespace App\Http\Controllers\Admin;

use App\CMS\Collections\Blog\BlogCollectionAdapter;
use App\CMS\Collections\Project\ProjectCollectionAdapter;
use App\CMS\Collections\Service\ServiceCollectionAdapter;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\Service;
use App\Models\Template;
use App\Services\ModuleService;
use App\Services\ProductTemplateContextBuilder;
use App\Services\ProductTemplateRuntime;
use App\Services\ProjectTemplateContextBuilder;
use App\Services\ProjectDiscoveryTemplateContextBuilder;
use App\Services\ProjectGalleryDiscoveryService;
use App\Services\SeoService;
use App\Services\SettingsService;
use App\Services\ServiceTemplateRuntime;
use App\Services\ServiceQueryService;
use App\Services\TemplateService;
use App\Support\SeoData;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PreviewController extends Controller
{
    public function page(Page $page, SeoService $seoService): View
    {
        $this->authorizePreview();

        return view('page', [
            'page' => $page,
            'seo' => $this->noindex($seoService->forPage($page)),
            'isPreview' => true,
        ]);
    }

    public function post(Post $post, SeoService $seoService): View
    {
        $this->authorizePreview();

        return view('blog.show', [
            'post' => $post->load('category'),
            'relatedPosts' => collect(),
            'seo' => $this->noindex($seoService->forPost($post)),
            'isPreview' => true,
        ]);
    }

    public function project(
        Project $project,
        SeoService $seoService,
        ModuleService $modules,
        TemplateService $templates,
        ProjectTemplateContextBuilder $contextBuilder,
    ): View {
        $this->authorizePreview();

        $template = $templates->findTemplateFor('project_single', $project);
        $context = $contextBuilder->build($project, $template, $modules->galleriesEnabled());

        return $templates->viewOrFallback($template, 'projects.show', [
            ...$context,
            'seo' => $this->noindex($seoService->forProject($project)),
            'isPreview' => true,
        ]);
    }

    public function gallery(Gallery $gallery, SeoService $seoService): View
    {
        $this->authorizePreview();

        return view('galleries.show', [
            'gallery' => $gallery->load(['category', 'project']),
            'relatedGalleries' => collect(),
            'seo' => $this->noindex($seoService->forGallery($gallery)),
            'isPreview' => true,
        ]);
    }

    public function product(
        Product $product,
        ProductTemplateRuntime $runtime,
    ): View {
        $this->authorizePreview();

        return $runtime->render($product, preview: true);
    }

    public function template(
        Template $template,
        Request $request,
        SeoService $seoService,
        ModuleService $modules,
        ProjectTemplateContextBuilder $contextBuilder,
        ProductTemplateContextBuilder $productContextBuilder,
        ProductTemplateRuntime $productRuntime,
        ServiceTemplateRuntime $serviceRuntime,
        ServiceQueryService $serviceQueries,
        ServiceCollectionAdapter $serviceCollections,
        BlogCollectionAdapter $blogCollections,
        ProjectCollectionAdapter $projectCollections,
        ProjectGalleryDiscoveryService $projectDiscovery,
        ProjectDiscoveryTemplateContextBuilder $projectDiscoveryContextBuilder,
        SettingsService $settings,
    ): View {
        $this->authorizePreview();

        if ($template->type === 'service_single') {
            $contextId = (int) $request->query('context_id');
            $service = $contextId > 0 ? Service::query()->find($contextId) : null;

            if ($service instanceof Service) {
                return $serviceRuntime->render($service, preview: true, template: $template);
            }

            return view('templates.preview-missing-context', [
                'message' => 'برای پیش‌نمایش این Template ابتدا یک خدمت را انتخاب کنید.',
                'seo' => new SeoData(
                    title: 'Service Template Preview',
                    description: 'Admin-only template preview.',
                    canonicalUrl: null,
                    robots: 'noindex, nofollow',
                    ogTitle: 'Service Template Preview',
                ),
                'isPreview' => true,
            ]);
        }

        if ($template->type === 'product_single') {
            $product = $this->findContextModel(Product::class, (int) $request->query('context_id'));

            if ($product instanceof Product) {
                return $productRuntime->render($product, preview: true, template: $template);
            }
        }

        if ($template->type === 'project_discovery_index') {
            $result = $projectDiscovery->discover(
                is_array($request->query('filters')) ? $request->query('filters') : [],
                (int) $settings->get('galleries_per_page', 12),
            );
            $heading = $settings->get('galleries_index_title', 'Galleries');
            $description = $settings->get('galleries_index_description', 'Browse image and video galleries.');

            return view('templates.render', [
                'template' => $template,
                'templateContext' => $projectDiscoveryContextBuilder->build($result, $heading, $description),
                'seo' => $this->noindex($seoService->forGalleryIndex()),
                'isPreview' => true,
            ]);
        }

        if ($template->type === 'service_index') {
            $heading = (string) $settings->get('services_index_title', 'خدمات حرفه‌ای برای رشد کسب‌وکار شما');
            $description = (string) $settings->get(
                'services_index_description',
                'با ترکیب تجربه، خلاقیت و فناوری‌های روز، مسیر رشد پایدار کسب‌وکارتان را هموار می‌سازیم.',
            );
            $services = $serviceQueries->paginateArchive((int) $settings->get('services_per_page', 12));
            $collection = $serviceCollections->adapt($services, $heading, $description);

            return view('templates.render', [
                'template' => $template,
                'templateContext' => [
                    'kind' => 'archive',
                    'type' => 'services',
                    'heading' => $heading,
                    'description' => $description,
                    'emptyMessage' => 'هنوز خدمتی منتشر نشده است.',
                    'collection' => $collection,
                    'isPreview' => true,
                ],
                'seo' => $this->noindex($seoService->forServiceIndex($heading, $description)),
                'isPreview' => true,
            ]);
        }

        if ($template->type === 'blog_index') {
            $posts = Post::query()
                ->with(['category', 'media'])
                ->published()
                ->latest('published_at')
                ->paginate(12);
            $collection = $blogCollections->adapt($posts);

            return view('templates.render', [
                'template' => $template,
                'templateContext' => [
                    'kind' => 'archive',
                    'type' => 'posts',
                    'heading' => 'وبلاگ',
                    'description' => null,
                    'emptyMessage' => 'هنوز نوشته‌ای منتشر نشده است.',
                    'collection' => $collection,
                    'isPreview' => true,
                ],
                'seo' => $this->noindex($seoService->forBlogIndex()),
                'isPreview' => true,
            ]);
        }

        if ($template->type === 'projects_index') {
            $heading = (string) $settings->get('projects_index_title', 'پروژه‌ها');
            $description = (string) $settings->get('projects_index_description', 'نمونه پروژه‌ها و تجربه‌های اجرایی ما.');
            $projects = Project::query()
                ->with(['category', 'media'])
                ->published()
                ->orderBy('sort_order')
                ->latest('published_at')
                ->paginate(12);

            return view('templates.render', [
                'template' => $template,
                'templateContext' => [
                    'kind' => 'archive',
                    'type' => 'projects',
                    'heading' => $heading,
                    'description' => $description,
                    'emptyMessage' => 'هنوز پروژه‌ای منتشر نشده است.',
                    'collection' => $projectCollections->adapt($projects, $heading, $description),
                    'isPreview' => true,
                ],
                'seo' => $this->noindex($seoService->forProjectIndex()),
                'isPreview' => true,
            ]);
        }

        $templateContext = $this->templateContext(
            $template,
            (int) $request->query('context_id'),
            $modules->galleriesEnabled(),
            $contextBuilder,
            $productContextBuilder,
        );

        if ($template->type === 'product_single') {
            $templateContext['isPreview'] = true;
        }

        return view('templates.render', [
            'template' => $template,
            'templateContext' => $templateContext,
            'seo' => $this->noindex($seoService->make([
                'title' => 'Template Preview: '.$template->title,
                'description' => 'Admin-only template preview.',
            ])),
            'isPreview' => true,
        ]);
    }

    private function authorizePreview(): void
    {
        abort_unless(Auth::user()?->is_admin, 403);
    }

    private function noindex(SeoData $seo): SeoData
    {
        return new SeoData(
            title: $seo->title,
            description: $seo->description,
            canonicalUrl: $seo->canonicalUrl,
            robots: 'noindex, nofollow',
            ogTitle: $seo->ogTitle,
            ogDescription: $seo->ogDescription,
            ogImage: $seo->ogImage,
            ogType: $seo->ogType,
            twitterCard: $seo->twitterCard,
            schema: $seo->schema,
        );
    }

    private function templateContext(
        Template $template,
        int $contextId,
        bool $galleriesEnabled,
        ProjectTemplateContextBuilder $contextBuilder,
        ProductTemplateContextBuilder $productContextBuilder,
    ): array {
        return match ($template->type) {
            'post_single' => $this->singleContext(
                'post',
                $this->findContextModel(Post::class, $contextId, ['category']),
            ),
            'project_single' => $this->projectSingleContext(
                $this->findContextModel(Project::class, $contextId),
                $template,
                $galleriesEnabled,
                $contextBuilder,
            ),
            'product_single' => $this->productSingleContext(
                $this->findContextModel(Product::class, $contextId),
                $template,
                $productContextBuilder,
            ),
            'gallery_single' => $this->singleContext(
                'gallery',
                $this->findContextModel(Gallery::class, $contextId, ['category', 'project']),
            ),
            'post_category' => $this->archiveContext(
                'posts',
                Post::query()->with('category')->published()->where('category_id', $this->findContextModel(Category::class, $contextId)?->id)->latest('published_at')->paginate(12),
                $this->findContextModel(Category::class, $contextId),
            ),
            'project_category' => $this->archiveContext(
                'projects',
                Project::query()->with('category')->published()->where('project_category_id', $this->findContextModel(ProjectCategory::class, $contextId)?->id)->latest('published_at')->paginate(12),
                $this->findContextModel(ProjectCategory::class, $contextId),
            ),
            'product_category' => $this->archiveContext(
                'products',
                Product::query()->with('category')->published()->where('product_category_id', $this->findContextModel(ProductCategory::class, $contextId)?->id)->latest('published_at')->paginate(12),
                $this->findContextModel(ProductCategory::class, $contextId),
            ),
            'gallery_category' => $this->archiveContext(
                'galleries',
                Gallery::query()->with(['category', 'project'])->published()->where('gallery_category_id', $this->findContextModel(GalleryCategory::class, $contextId)?->id)->latest('published_at')->paginate(12),
                $this->findContextModel(GalleryCategory::class, $contextId),
            ),
            'blog_index' => $this->archiveContext('posts', Post::query()->with('category')->published()->latest('published_at')->paginate(12), null, 'Blog'),
            'projects_index' => $this->archiveContext('projects', Project::query()->with('category')->published()->latest('published_at')->paginate(12), null, 'Projects'),
            'shop_index' => $this->archiveContext('products', Product::query()->with('category')->published()->latest('published_at')->paginate(12), null, 'فروشگاه'),
            'galleries_index' => $this->archiveContext('galleries', Gallery::query()->with(['category', 'project'])->published()->latest('published_at')->paginate(12), null, 'Galleries'),
            default => [
                'kind' => 'template',
                'type' => $template->type,
            ],
        };
    }

    private function findContextModel(string $modelClass, int $id = 0, array $with = []): ?Model
    {
        $query = $modelClass::query()->with($with);

        if ($id > 0) {
            return $query->find($id);
        }

        return $query->first();
    }

    private function singleContext(string $type, ?Model $model): array
    {
        return [
            'kind' => 'single',
            'type' => $type,
            'model' => $model,
            'related' => collect(),
        ];
    }

    private function projectSingleContext(
        ?Project $project,
        Template $template,
        bool $galleriesEnabled,
        ProjectTemplateContextBuilder $contextBuilder,
    ): array {
        if (! $project) {
            return $this->singleContext('project', null);
        }

        return $contextBuilder->build($project, $template, $galleriesEnabled)['templateContext'];
    }

    private function productSingleContext(
        ?Product $product,
        Template $template,
        ProductTemplateContextBuilder $contextBuilder,
    ): array {
        if (! $product) {
            return $this->singleContext('product', null);
        }

        $context = $contextBuilder->build(
            $product,
            $contextBuilder->relatedLimit($template),
        );

        return [
            'kind' => 'single',
            'type' => 'product',
            'model' => $product,
            'related' => $context['relatedProducts'],
            ...$context,
        ];
    }

    private function archiveContext(string $type, mixed $items, ?Model $category = null, ?string $heading = null): array
    {
        return [
            'kind' => 'archive',
            'type' => $type,
            'items' => $items,
            'category' => $category,
            'activeCategory' => $category,
            'heading' => $heading ?: ($category?->name ?? $category?->title),
            'description' => $category?->description,
            'emptyMessage' => 'No preview items found.',
        ];
    }
}
