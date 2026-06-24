@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <section class="projects-index">
        <header class="blog-index__header">
            <h1>{{ $heading ?? 'پروژه‌ها' }}</h1>

            @if (! empty($description))
                <p>{{ $description }}</p>
            @endif
        </header>

        @if (($categories ?? collect())->isNotEmpty())
            <nav class="archive-nav" aria-label="دسته‌بندی پروژه‌ها">
                <a href="{{ route('projects.index') }}" @class(['is-active' => empty($activeCategory)])>همه</a>

                @foreach ($categories as $category)
                    <a href="{{ route('projects.category', $category->slug) }}" @class(['is-active' => isset($activeCategory) && $activeCategory->is($category)])>
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="blog-index__grid">
            @forelse ($projects as $project)
                @include('projects.partials.card', ['project' => $project])
            @empty
                <p class="blog-index__empty">{{ $emptyMessage ?? 'هنوز پروژه‌ای منتشر نشده است.' }}</p>
            @endforelse
        </div>

        <div class="blog-index__pagination">
            {{ $projects->links() }}
        </div>
    </section>
@endsection
