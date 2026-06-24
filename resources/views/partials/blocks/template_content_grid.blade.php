@php
    $items = $context['items'] ?? collect();
    $type = $context['type'] ?? null;
    $emptyMessage = $data['empty_message'] ?? $context['emptyMessage'] ?? 'No items found.';
@endphp

<section class="content-block">
    @if (! empty($data['title']))
        <div class="section-heading">
            <h2>{{ $data['title'] }}</h2>
        </div>
    @endif

    <div class="blog-index__grid">
        @forelse ($items as $item)
            @switch($type)
                @case('posts')
                    <article class="blog-card">
                        <a class="blog-card__image" href="{{ route('blog.show', $item->slug) }}" aria-label="{{ $item->title }}">
                            @if ($item->featuredImageUrl('thumb'))
                                <img src="{{ $item->featuredImageUrl('thumb') }}" alt="{{ $item->title }}">
                            @else
                                <span>{{ $item->title }}</span>
                            @endif
                        </a>

                        <div class="blog-card__body">
                            <h2><a href="{{ route('blog.show', $item->slug) }}">{{ $item->title }}</a></h2>

                            @if ($item->published_at)
                                <time datetime="{{ $item->published_at->toDateString() }}">{{ $item->published_at->toFormattedDateString() }}</time>
                            @endif

                            @if ($item->category)
                                <a href="{{ route('blog.category', $item->category->slug) }}">{{ $item->category->title }}</a>
                            @endif
                        </div>
                    </article>
                    @break

                @case('projects')
                    @include('projects.partials.card', ['project' => $item])
                    @break

                @case('products')
                    @include('shop.partials.card', ['product' => $item])
                    @break

                @case('galleries')
                    @include('galleries.partials.card', ['gallery' => $item])
                    @break
            @endswitch
        @empty
            <p class="blog-index__empty">{{ $emptyMessage }}</p>
        @endforelse
    </div>

    @if (is_object($items) && method_exists($items, 'links'))
        <div class="blog-index__pagination">
            {{ $items->links() }}
        </div>
    @endif
</section>
