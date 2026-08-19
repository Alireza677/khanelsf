<?php

namespace App\Http\Controllers;

use App\CMS\Collections\Blog\BlogCollectionAdapter;
use App\Models\Category;
use App\Models\Post;
use App\Services\SeoService;
use App\Services\TemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(SeoService $seoService, TemplateService $templates, BlogCollectionAdapter $collections): View
    {
        $posts = Post::query()
            ->with(['category', 'media'])
            ->published()
            ->latest('published_at')
            ->paginate(12);

        $template = $templates->findTemplateFor('blog_index');
        $collection = $collections->adapt($posts);

        return $templates->viewOrFallback($template, 'blog.index', [
            'posts' => $posts,
            'collection' => $collection,
            'seo' => $seoService->forBlogIndex(),
            'templateContext' => [
                'kind' => 'archive',
                'type' => 'posts',
                'items' => $posts,
                'heading' => 'Blog',
                'description' => null,
                'emptyMessage' => 'No posts have been published yet.',
                'collection' => $collection,
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

    public function category(string $slug, SeoService $seoService, TemplateService $templates, BlogCollectionAdapter $collections): View
    {
        $category = Category::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Post::query()
            ->with(['category', 'media'])
            ->published()
            ->whereBelongsTo($category)
            ->latest('published_at')
            ->paginate(12);

        $template = $templates->findTemplateFor('post_category', $category);
        $collection = $collections->adapt(
            $posts,
            $category->title,
            $category->description,
            'No posts have been published in this category yet.',
        );

        return $templates->viewOrFallback($template, 'blog.index', [
            'posts' => $posts,
            'collection' => $collection,
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
                'collection' => $collection,
            ],
        ]);
    }

    public function search(Request $request, SeoService $seoService, BlogCollectionAdapter $collections): View
    {
        $query = trim((string) $request->query('q', ''));

        $posts = Post::query()
            ->with(['category', 'media'])
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

        $heading = $query !== '' ? 'Search results for "'.$query.'"' : 'Search';

        return view('blog.index', [
            'posts' => $posts,
            'collection' => $collections->adapt(
                $posts,
                $heading,
                null,
                'No matching posts were found.',
            ),
            'heading' => $heading,
            'emptyMessage' => 'No matching posts were found.',
            'searchQuery' => $query,
            'seo' => $seoService->forBlogSearch($query),
            'template' => null,
        ]);
    }
}
