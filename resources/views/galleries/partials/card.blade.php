<article class="blog-card">
    <a class="blog-card__image" href="{{ route('galleries.show', $gallery->slug) }}" aria-label="{{ $gallery->title }}">
        @if ($gallery->cardImageUrl())
            <img src="{{ $gallery->cardImageUrl() }}" alt="{{ $gallery->title }}">
        @else
            <span>{{ $gallery->title }}</span>
        @endif

        @if (in_array($gallery->type, ['video', 'mixed'], true))
            <span class="media-type-badge">{{ ucfirst($gallery->type) }}</span>
        @endif
    </a>

    <div class="blog-card__body">
        <h2><a href="{{ route('galleries.show', $gallery->slug) }}">{{ $gallery->title }}</a></h2>

        <div class="post-meta">
            <span>{{ ucfirst($gallery->type) }}</span>

            @if ($gallery->category)
                <a href="{{ route('galleries.category', $gallery->category->slug) }}">{{ $gallery->category->name }}</a>
            @endif
        </div>

        @if ($gallery->excerpt)
            <p>{{ $gallery->excerpt }}</p>
        @endif
    </div>
</article>
