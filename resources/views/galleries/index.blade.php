@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <section class="projects-index">
        <header class="blog-index__header">
            <h1>{{ $heading ?? 'گالری‌ها' }}</h1>

            @if (! empty($description))
                <p>{{ $description }}</p>
            @endif
        </header>

        @if (($categories ?? collect())->isNotEmpty())
            <nav class="archive-nav" aria-label="دسته‌بندی گالری‌ها">
                <a href="{{ route('galleries.index') }}" @class(['is-active' => empty($activeCategory)])>همه</a>

                @foreach ($categories as $category)
                    <a href="{{ route('galleries.category', $category->slug) }}" @class(['is-active' => isset($activeCategory) && $activeCategory->is($category)])>
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="blog-index__grid">
            @forelse ($galleries as $gallery)
                @include('galleries.partials.card', ['gallery' => $gallery])
            @empty
                <p class="blog-index__empty">{{ $emptyMessage ?? 'هنوز گالری‌ای منتشر نشده است.' }}</p>
            @endforelse
        </div>

        <div class="blog-index__pagination">
            {{ $galleries->links() }}
        </div>
    </section>
@endsection
