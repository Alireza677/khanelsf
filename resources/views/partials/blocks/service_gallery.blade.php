@php
    $data = app(\App\CMS\Blocks\Service\ServiceGalleryBlock::class)->normalize(is_array($data ?? null) ? $data : []);
    $gallery = collect(data_get($context, 'media.gallery', []))
        ->filter(fn ($image): bool => filled(data_get($image, 'url')))
        ->values();
@endphp

@if ($gallery->isNotEmpty())
    <section class="content-block service-section service-gallery service-gallery--{{ $data['settings']['variant'] }}" dir="rtl">
        @if ($data['content']['title'])
            @include('partials.blocks._heading', ['title' => $data['content']['title'], 'tag' => data_get($data, 'settings.heading_tag', 'h2')])
        @endif

        <div class="block-gallery service-gallery__grid service-grid--{{ $data['settings']['columns'] }}">
            @foreach ($gallery as $image)
                @if ($data['settings']['lightbox'])
                    <button
                        class="gallery-lightbox-trigger"
                        type="button"
                        data-gallery-lightbox-src="{{ data_get($image, 'url') }}"
                        data-gallery-lightbox-alt="{{ data_get($image, 'name') ?: data_get($context, 'content.name', 'تصویر خدمت') }}"
                    >
                        <img
                            src="{{ data_get($image, 'url') }}"
                            alt="{{ data_get($image, 'name') ?: data_get($context, 'content.name', 'تصویر خدمت') }}"
                            loading="lazy"
                        >
                    </button>
                @else
                    <img
                        src="{{ data_get($image, 'url') }}"
                        alt="{{ data_get($image, 'name') ?: data_get($context, 'content.name', 'تصویر خدمت') }}"
                        loading="lazy"
                    >
                @endif
            @endforeach
        </div>
    </section>
@endif
