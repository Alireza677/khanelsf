@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <section class="blog-index">
        @if (isset($collection))
            <form method="get" action="{{ route('blog.search') }}">
                <label for="blog-search">جستجوی نوشته‌ها</label>
                <input id="blog-search" name="q" type="search" value="{{ $searchQuery ?? request('q') }}" placeholder="جستجوی مقاله‌ها">
                <button class="button" type="submit">جستجو</button>
            </form>

            @include('partials.presentations.collection', ['collection' => $collection])
        @else
        <header class="blog-index__header">
            <h1>{{ $heading ?? 'وبلاگ' }}</h1>

            <form method="get" action="{{ route('blog.search') }}">
                <label for="blog-search">جستجوی نوشته‌ها</label>
                <input id="blog-search" name="q" type="search" value="{{ $searchQuery ?? request('q') }}" placeholder="جستجوی مقاله‌ها">
                <button class="button" type="submit">جستجو</button>
            </form>
        </header>

        <div class="blog-index__grid">
            @forelse ($posts as $post)
                <article class="blog-card">
                    <a class="blog-card__image" href="{{ route('blog.show', $post->slug) }}" aria-label="{{ $post->title }}">
                        @if ($post->featuredImageUrl('thumb'))
                            <img src="{{ $post->featuredImageUrl('thumb') }}" alt="{{ $post->title }}">
                        @else
                            <span>{{ $post->title }}</span>
                        @endif
                    </a>

                    <div class="blog-card__body">
                        <h2><a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h2>

                        @if ($post->published_at)
                            <x-persian-date :value="$post->published_at" format="weekday" :datetime="$post->published_at->toIso8601String()" />
                        @endif

                        @if ($post->category)
                            <a href="{{ route('blog.category', $post->category->slug) }}">{{ $post->category->title }}</a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="blog-index__empty">{{ $emptyMessage ?? 'هنوز نوشته‌ای منتشر نشده است.' }}</p>
            @endforelse
        </div>

        <div class="blog-index__pagination">
            {{ $posts->links() }}
        </div>
        @endif
    </section>
@endsection
