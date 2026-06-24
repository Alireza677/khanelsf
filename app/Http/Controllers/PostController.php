<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Services\SeoService;
use App\Services\TemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(SeoService $seoService, TemplateService $templates): View
    {
        $posts = Post::query()
            ->with('category')
            ->published()
            ->latest('published_at')
            ->paginate(12);

        $template = $templates->findTemplateFor('blog_index');

        return $templates->viewOrFallback($template, 'blog.index', [
            'posts' => $posts,
            'seo' => $seoService->forBlogIndex(),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'posts',
                'items' => $posts,
                'heading' => 'Blog',
                'description' => null,
                'emptyMessage' => 'No posts have been published yet.',
            ],
        ]);
    }

    public function show(string $slug, SeoService $seoService, TemplateService $templates): View
    {
        $post = Post::query()
            ->with('category')
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        $relatedPosts = Post::query()
            ->with('category')
            ->published()
            ->whereKeyNot($post->getKey())
            ->when($post->category_id, fn ($query) => $query->where('category_id', $post->category_id))
            ->latest('published_at')
            ->take(3)
            ->get();

        $template = $templates->findTemplateFor('post_single', $post);

        return $templates->viewOrFallback($template, 'blog.show', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'seo' => $seoService->forPost($post),
            'templateContext' => [
                'kind' => 'single',
                'type' => 'post',
                'model' => $post,
                'related' => $relatedPosts,
            ],
        ]);
    }

    public function category(string $slug, SeoService $seoService, TemplateService $templates): View
    {
        $category = Category::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Post::query()
            ->with('category')
            ->published()
            ->whereBelongsTo($category)
            ->latest('published_at')
            ->paginate(12);

        $template = $templates->findTemplateFor('post_category', $category);

        return $templates->viewOrFallback($template, 'blog.index', [
            'posts' => $posts,
            'heading' => $category->title,
            'emptyMessage' => 'No posts have been published in this category yet.',
            'seo' => $seoService->forBlogCategory($category),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'posts',
                'items' => $posts,
                'category' => $category,
                'activeCategory' => $category,
                'heading' => $category->title,
                'description' => $category->description,
                'emptyMessage' => 'No posts have been published in this category yet.',
            ],
        ]);
    }

    public function search(Request $request, SeoService $seoService): View
    {
        $query = trim((string) $request->query('q', ''));

        $posts = Post::query()
            ->with('category')
            ->published()
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($builder) use ($query): void {
                    $builder
                        ->where('title', 'like', "%{$query}%")
                        ->orWhere('excerpt', 'like', "%{$query}%")
                        ->orWhere('content', 'like', "%{$query}%");
                });
            })
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('blog.index', [
            'posts' => $posts,
            'heading' => $query !== '' ? 'Search results for "'.$query.'"' : 'Search',
            'emptyMessage' => 'No matching posts were found.',
            'searchQuery' => $query,
            'seo' => $seoService->forBlogSearch($query),
            'template' => null,
        ]);
    }
}
