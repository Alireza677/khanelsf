@php
    $data = app(\App\CMS\Blocks\Product\ProductGalleryBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $gallery = collect(data_get($context, 'media.gallery', []));
@endphp

@if ($gallery->isNotEmpty())
    <section class="content-block product-gallery" dir="rtl">
        @include('partials.blocks._heading', ['title' => $data['content']['title'] ?: 'گالری محصول', 'tag' => data_get($data, 'settings.heading_tag', 'h2')])

        <div class="gallery-grid product-gallery__grid product-gallery__grid--columns-{{ $data['settings']['columns'] }}">
            @foreach ($gallery as $image)
                @if (filled(data_get($image, 'url')))
                    @if ($data['settings']['lightbox'])
                        <a href="{{ data_get($image, 'url') }}" target="_blank" rel="noopener noreferrer">
                    @endif
                    <img
                        src="{{ data_get($image, 'url') }}"
                        alt="{{ data_get($image, 'name') ?: 'تصویر محصول' }}"
                        loading="lazy"
                    >
                    @if ($data['settings']['lightbox'])
                        </a>
                    @endif
                @endif
            @endforeach
        </div>
    </section>
@endif
