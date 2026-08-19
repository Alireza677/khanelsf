@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <section class="projects-index project-gallery-archive">
        @if (($categories ?? collect())->isNotEmpty())
            <nav class="archive-nav" aria-label="دسته‌بندی پروژه‌ها">
                <a href="{{ route('galleries.index') }}" @class(['is-active' => empty($activeCategory)])>همه</a>

                @foreach ($categories as $category)
                    <a href="{{ route('projects.category', $category->slug) }}" @class(['is-active' => isset($activeCategory) && $activeCategory->is($category)])>
                        {{ $category->name }}
                    </a>
                @endforeach
            </nav>
        @endif

        @include('partials.presentations.collection', ['collection' => $collection])
    </section>
@endsection
