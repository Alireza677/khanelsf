<article class="blog-card">
    <a class="blog-card__image" href="{{ route('shop.show', $product->slug) }}" aria-label="{{ $product->title }}">
        @if ($product->featuredImageUrl('thumb'))
            <img src="{{ $product->featuredImageUrl('thumb') }}" alt="{{ $product->title }}">
        @else
            <span>{{ $product->title }}</span>
        @endif
    </a>

    <div class="blog-card__body">
        <h2><a href="{{ route('shop.show', $product->slug) }}">{{ $product->title }}</a></h2>

        @if ($product->category)
            <a href="{{ route('shop.category', $product->category->slug) }}">{{ $product->category->name }}</a>
        @endif

        <p class="product-price">
            @include('shop.partials.price', ['product' => $product])
        </p>

        @unless ($product->has_stock && $product->stock_status === 'in_stock')
            <span class="stock-badge">ناموجود</span>
        @endunless

        @if ($product->excerpt)
            <p>{{ $product->excerpt }}</p>
        @endif
    </div>
</article>
