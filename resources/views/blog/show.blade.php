@extends('layouts.app')

@section('content')
    @if (! empty($template?->blocks))
        @include('partials.page-blocks', ['blocks' => $template->blocks])
    @endif

    <article>
        <h1>{{ $post->title }}</h1>

        @if ($post->published_at)
            <p><x-persian-date :value="$post->published_at" format="weekday" :datetime="$post->published_at->toIso8601String()" /></p>
        @endif

        @if ($post->category)
            <p>{{ $post->category->title }}</p>
        @endif

        @if ($post->featuredImageUrl())
            <img src="{{ $post->featuredImageUrl() }}" alt="{{ $post->title }}">
        @endif

        {!! $post->content !!}
    </article>

    @if (($relatedPosts ?? collect())->isNotEmpty())
        <section>
            <div class="section-heading">
                <h2>Related Posts</h2>
            </div>

            <div class="latest-posts">
                @foreach ($relatedPosts as $relatedPost)
                    <article class="post-card">
                        <div class="post-card__body">
                            <h3><a href="{{ route('blog.show', $relatedPost->slug) }}">{{ $relatedPost->title }}</a></h3>

                            @if ($relatedPost->excerpt)
                                <p>{{ $relatedPost->excerpt }}</p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
@endsection
