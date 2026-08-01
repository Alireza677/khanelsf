@php
    $data = app(\App\CMS\Blocks\Product\ProductOverviewBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $product = ($context['product'] ?? null) instanceof \App\Models\Product ? $context['product'] : null;
@endphp

@if ($product && filled($product->content))
    <section class="content-block product-overview" dir="rtl">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="prose">{!! $product->content !!}</div>
    </section>
@endif
