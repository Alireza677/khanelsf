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
            @if ($product->hasSalePrice())
                <span class="product-price__sale">${{ number_format($product->currentPrice(), 2) }}</span>
                <span class="product-price__regular">${{ number_format((float) $product->price, 2) }}</span>
            @else
                <span>${{ number_format($product->currentPrice(), 2) }}</span>
            @endif
        </p>

        @unless ($product->isPurchasable())
            <span class="stock-badge">Out of stock</span>
        @endunless

        @if ($product->excerpt)
            <p>{{ $product->excerpt }}</p>
        @endif
    </div>
</article>
