<?php

namespace App\Http\Controllers\Admin;

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
use App\Models\Template;
use App\Services\SeoService;
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

    public function project(Project $project, SeoService $seoService): View
    {
        $this->authorizePreview();

        return view('projects.show', [
            'project' => $project->load('category'),
            'relatedProjects' => collect(),
            'projectGalleries' => collect(),
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

    public function product(Product $product, SeoService $seoService): View
    {
        $this->authorizePreview();

        return view('shop.show', [
            'product' => $product->load('category'),
            'relatedProducts' => collect(),
            'seo' => $this->noindex($seoService->forProduct($product)),
            'isPreview' => true,
        ]);
    }

    public function template(Template $template, Request $request, SeoService $seoService): View
    {
        $this->authorizePreview();

        return view('templates.render', [
            'template' => $template,
            'templateContext' => $this->templateContext($template, (int) $request->query('context_id')),
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

    private function templateContext(Template $template, int $contextId = 0): array
    {
        return match ($template->type) {
            'post_single' => $this->singleContext(
                'post',
                $this->findContextModel(Post::class, $contextId, ['category']),
            ),
            'project_single' => $this->singleContext(
                'project',
                $this->findContextModel(Project::class, $contextId, ['category']),
            ),
            'product_single' => $this->singleContext(
                'product',
                $this->findContextModel(Product::class, $contextId, ['category']),
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
