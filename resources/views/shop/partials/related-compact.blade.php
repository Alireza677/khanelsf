<section class="product-related-compact" dir="rtl" aria-label="{{ $data['content']['title'] ?: 'محصولات مرتبط' }}">
    @include('partials.blocks._heading', ['title' => $data['content']['title'] ?: 'محصولات مرتبط', 'tag' => $data['settings']['heading_tag']])
    <div class="product-related-compact__grid">
        @foreach ($relatedProducts as $relatedProduct)
            @php($card = $context['relatedCards'][$relatedProduct->id] ?? null)
            @if ($card)
                <article class="product-related-compact__card">
                    <a class="product-related-compact__image" href="{{ $card['url'] }}" aria-label="{{ $relatedProduct->title }}">
                        @if ($card['image'])<img src="{{ $card['image'] }}" alt="{{ $relatedProduct->title }}" loading="lazy" decoding="async">@else<x-heroicon-o-photo aria-hidden="true" />@endif
                    </a>
                    <div><h3><a href="{{ $card['url'] }}">{{ $relatedProduct->title }}</a></h3>
                        <p>@include('shop.partials.price', ['product' => $relatedProduct, 'pricePresentation' => $card['priceFreshness'], 'persianPriceDigits' => true])</p>
                    </div>
                </article>
            @endif
        @endforeach
    </div>
</section>
