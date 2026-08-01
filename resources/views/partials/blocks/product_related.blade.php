@php
    $data = app(\App\CMS\Blocks\Product\ProductRelatedBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $relatedProducts = collect($context['relatedProducts'] ?? [])
        ->take($data['settings']['limit']);
@endphp

@if ($relatedProducts->isNotEmpty())
    <section class="content-block product-related" dir="rtl">
        @include('partials.blocks._heading', ['title' => $data['content']['title'] ?: 'محصولات مرتبط', 'tag' => data_get($data, 'settings.heading_tag', 'h2')])

        <div class="product-grid">
            @foreach ($relatedProducts as $relatedProduct)
                <article class="product-card">
                    <h3>
                        <a href="{{ route('shop.show', $relatedProduct->slug) }}">{{ $relatedProduct->title }}</a>
                    </h3>

                    @if ($relatedProduct->excerpt)
                        <p>{{ $relatedProduct->excerpt }}</p>
                    @endif

                    <p>{{ number_format($relatedProduct->currentPrice()) }} تومان</p>
                </article>
            @endforeach
        </div>
    </section>
@endif
